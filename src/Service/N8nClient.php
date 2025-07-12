<?php

declare(strict_types=1);

namespace Freema\N8nBundle\Service;

use DateTimeImmutable;
use Freema\N8nBundle\Contract\N8nClientInterface;
use Freema\N8nBundle\Contract\N8nPayloadInterface;
use Freema\N8nBundle\Contract\N8nResponseHandlerInterface;
use Freema\N8nBundle\Domain\N8nConfig;
use Freema\N8nBundle\Domain\N8nRequest;
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
        private readonly ?RetryHandler $retryHandler = null,
        private readonly ?CircuitBreaker $circuitBreaker = null
    ) {}
    
    public function send(N8nPayloadInterface $payload, string $workflowId, CommunicationMode $mode = CommunicationMode::FIRE_AND_FORGET): string
    {
        $request = new N8nRequest(
            uuid: $this->uuidGenerator->generate(),
            workflowId: $workflowId,
            payload: $payload,
            mode: $mode,
            clientId: $this->config->clientId,
            createdAt: new DateTimeImmutable()
        );
        
        $this->requestTracker->trackRequest($request);
        
        try {
            $this->circuitBreaker?->checkAndThrow();
            
            $operation = fn() => $this->httpClient->sendWebhook($request);
            
            $response = $this->retryHandler !== null
                ? $this->retryHandler->executeWithRetry($operation, $request)
                : $operation();
            
            if ($response->getStatusCode() >= 400) {
                $exception = new N8nCommunicationException(
                    'N8n webhook returned error: ' . $response->getContent(false),
                    $response->getStatusCode()
                );
                $this->circuitBreaker?->recordFailure();
                throw $exception;
            }
            
            $this->circuitBreaker?->recordSuccess();
            return $request->uuid;
        } catch (\Throwable $e) {
            $this->requestTracker->completeRequest($request->uuid);
            throw $e;
        }
    }
    
    public function sendWithCallback(N8nPayloadInterface $payload, string $workflowId, N8nResponseHandlerInterface $handler): string
    {
        $callbackUrl = $this->urlGenerator->generate('n8n_callback', [], UrlGeneratorInterface::ABSOLUTE_URL);
        
        $request = new N8nRequest(
            uuid: $this->uuidGenerator->generate(),
            workflowId: $workflowId,
            payload: $payload,
            mode: CommunicationMode::ASYNC_WITH_CALLBACK,
            clientId: $this->config->clientId,
            createdAt: new DateTimeImmutable(),
            responseHandler: $handler,
            callbackUrl: $callbackUrl
        );
        
        $this->requestTracker->trackRequest($request);
        
        try {
            $response = $this->httpClient->sendWebhook($request);
            
            if ($response->getStatusCode() >= 400) {
                throw new N8nCommunicationException(
                    'N8n webhook returned error: ' . $response->getContent(false),
                    $response->getStatusCode()
                );
            }
            
            return $request->uuid;
        } catch (\Throwable $e) {
            $this->requestTracker->completeRequest($request->uuid);
            throw $e;
        }
    }
    
    public function sendSync(N8nPayloadInterface $payload, string $workflowId, int $timeoutSeconds = 30): array
    {
        $request = new N8nRequest(
            uuid: $this->uuidGenerator->generate(),
            workflowId: $workflowId,
            payload: $payload,
            mode: CommunicationMode::SYNC,
            clientId: $this->config->clientId,
            createdAt: new DateTimeImmutable(),
            timeoutSeconds: $timeoutSeconds
        );
        
        $response = $this->httpClient->sendWebhook($request);
        
        if ($response->getStatusCode() >= 400) {
            throw new N8nCommunicationException(
                'N8n webhook returned error: ' . $response->getContent(false),
                $response->getStatusCode()
            );
        }
        
        return $response->toArray();
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