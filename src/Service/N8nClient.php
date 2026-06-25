<?php

declare(strict_types=1);

namespace Freema\N8nBundle\Service;

use Freema\N8nBundle\Contract\N8nClientInterface;
use Freema\N8nBundle\Contract\N8nPayloadInterface;
use Freema\N8nBundle\Contract\N8nResponseHandlerInterface;
use Freema\N8nBundle\Domain\N8nConfig;
use Freema\N8nBundle\Domain\N8nRequest;
use Freema\N8nBundle\Dto\N8nHttpResult;
use Freema\N8nBundle\Dto\N8nResponse;
use Freema\N8nBundle\Enum\CommunicationMode;
use Freema\N8nBundle\Exception\N8nCommunicationException;
use Freema\N8nBundle\Http\N8nHttpClient;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class N8nClient implements N8nClientInterface
{
    public function __construct(
        private readonly N8nConfig $config,
        private readonly N8nHttpClient $httpClient,
        private readonly UuidGenerator $uuidGenerator,
        private readonly RequestTracker $requestTracker,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly ResponseMapper $responseMapper,
        private readonly ?RetryHandler $retryHandler = null,
        private readonly ?CircuitBreaker $circuitBreaker = null,
        private readonly string $callbackRouteName = 'n8n_callback',
    ) {
    }

    public function send(N8nPayloadInterface $payload, string $workflowId, CommunicationMode $mode = CommunicationMode::FIRE_AND_FORGET): N8nResponse
    {
        $request = new N8nRequest(
            uuid: $this->uuidGenerator->generate(),
            workflowId: $workflowId,
            payload: $payload,
            mode: $mode,
            clientId: $this->config->clientId,
            createdAt: new \DateTimeImmutable(),
            requestMethod: $payload->getN8nRequestMethod(),
        );

        $this->requestTracker->trackRequest($request);

        $this->circuitBreaker?->checkAndThrow();

        // Treat 5xx as a retryable failure so the retry handler re-attempts it.
        // Transport/timeout errors already arrive as typed N8nException from sendWebhook().
        $operation = function () use ($request): N8nHttpResult {
            $result = $this->httpClient->sendWebhook($request);

            if ($result->statusCode >= 500) {
                throw new N8nCommunicationException('N8n webhook returned error: '.$result->content, $result->statusCode);
            }

            return $result;
        };

        // Any transport/timeout/5xx failure must record a circuit breaker failure,
        // otherwise the breaker never opens on outages.
        try {
            $result = $this->retryHandler !== null
                ? $this->retryHandler->executeWithRetry($operation, $request)
                : $operation();
        } catch (\Throwable $e) {
            $this->circuitBreaker?->recordFailure();
            $this->requestTracker->completeRequest($request->uuid);
            throw $e;
        }

        // Remaining 4xx responses are client errors and are not retried.
        if ($result->statusCode >= 400) {
            $this->circuitBreaker?->recordFailure();
            $this->requestTracker->completeRequest($request->uuid);

            throw new N8nCommunicationException('N8n webhook returned error: '.$result->content, $result->statusCode);
        }

        $this->circuitBreaker?->recordSuccess();

        try {
            $responseData = $result->toArray();

            // Map response to entity if class is specified
            $mappedResponse = null;
            if (method_exists($payload, 'getN8nResponseClass')) {
                $responseClass = $payload->getN8nResponseClass();
                if ($responseClass !== null && class_exists($responseClass)) {
                    try {
                        $mappedResponse = $this->responseMapper->mapToClass($responseData, $responseClass);
                    } catch (\Exception $e) {
                        // Log mapping error but continue with raw data
                    }
                }
            }

            // Handle response through custom handler if provided
            if (method_exists($payload, 'getN8nResponseHandler')) {
                $responseHandler = $payload->getN8nResponseHandler();
                if ($responseHandler !== null) {
                    $responseHandler->handleN8nResponse($responseData, $request->uuid);
                }
            }

            $this->requestTracker->completeRequest($request->uuid);

            return new N8nResponse(
                uuid: $request->uuid,
                response: $responseData,
                mappedResponse: $mappedResponse,
                statusCode: $result->statusCode,
            );
        } catch (\Throwable $e) {
            $this->requestTracker->completeRequest($request->uuid);
            throw $e;
        }
    }

    public function sendWithCallback(N8nPayloadInterface $payload, string $workflowId, N8nResponseHandlerInterface $handler): string
    {
        $callbackUrl = $this->urlGenerator->generate($this->callbackRouteName, [], UrlGeneratorInterface::ABSOLUTE_URL);

        $request = new N8nRequest(
            uuid: $this->uuidGenerator->generate(),
            workflowId: $workflowId,
            payload: $payload,
            mode: CommunicationMode::ASYNC_WITH_CALLBACK,
            clientId: $this->config->clientId,
            createdAt: new \DateTimeImmutable(),
            requestMethod: $payload->getN8nRequestMethod(),
            responseHandler: $handler,
            callbackUrl: $callbackUrl,
        );

        $this->requestTracker->trackRequest($request);

        try {
            $result = $this->httpClient->sendWebhook($request);

            if ($result->statusCode >= 400) {
                throw new N8nCommunicationException('N8n webhook returned error: '.$result->content, $result->statusCode);
            }

            return $request->uuid;
        } catch (\Throwable $e) {
            $this->requestTracker->completeRequest($request->uuid);
            throw $e;
        }
    }

    public function sendSync(N8nPayloadInterface $payload, string $workflowId, int $timeoutSeconds = 30): N8nResponse
    {
        $request = new N8nRequest(
            uuid: $this->uuidGenerator->generate(),
            workflowId: $workflowId,
            payload: $payload,
            mode: CommunicationMode::SYNC,
            clientId: $this->config->clientId,
            createdAt: new \DateTimeImmutable(),
            requestMethod: $payload->getN8nRequestMethod(),
            timeoutSeconds: $timeoutSeconds,
        );

        $result = $this->httpClient->sendWebhook($request);

        if ($result->statusCode >= 400) {
            throw new N8nCommunicationException('N8n webhook returned error: '.$result->content, $result->statusCode);
        }

        return new N8nResponse(
            uuid: $request->uuid,
            response: $result->toArray(),
            mappedResponse: null,
            statusCode: $result->statusCode,
        );
    }

    public function getClientId(): string
    {
        return $this->config->clientId;
    }

    public function isHealthy(): bool
    {
        return $this->httpClient->healthCheck();
    }
}
