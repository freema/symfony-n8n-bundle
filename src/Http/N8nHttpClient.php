<?php

declare(strict_types=1);

namespace Freema\N8nBundle\Http;

use Freema\N8nBundle\Domain\N8nConfig;
use Freema\N8nBundle\Domain\N8nRequest;
use Freema\N8nBundle\Dto\N8nHttpResult;
use Freema\N8nBundle\Enum\RequestMethod;
use Freema\N8nBundle\Exception\N8nCommunicationException;
use Freema\N8nBundle\Exception\N8nTimeoutException;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\Exception\TimeoutExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class N8nHttpClient
{
    private HttpClientInterface $httpClient;

    public function __construct(
        private readonly N8nConfig $config,
        ?HttpClientInterface $httpClient = null,
    ) {
        $httpClientOptions = [
            'timeout' => $this->config->timeoutSeconds,
            'headers' => array_merge([
                'Content-Type' => 'application/json',
                'User-Agent' => 'Symfony N8n Bundle/1.0',
            ], $this->config->defaultHeaders),
        ];

        if ($this->config->proxy !== null) {
            $httpClientOptions['proxy'] = $this->config->proxy;
        }

        $this->httpClient = $httpClient ?? HttpClient::create($httpClientOptions);
    }

    public function sendWebhook(N8nRequest $request): N8nHttpResult
    {
        if ($this->config->dryRun) {
            $payload = json_encode(['dry_run' => true, 'uuid' => $request->uuid]);

            return new N8nHttpResult(200, $payload !== false ? $payload : '{}');
        }

        $url = $this->config->getWebhookUrl($request->workflowId);
        $payload = $request->toWebhookPayload();
        $requestMethod = $request->requestMethod;
        $httpMethod = $requestMethod->getHttpMethod();

        $options = [
            'timeout' => $request->timeoutSeconds ?? $this->config->timeoutSeconds,
        ];

        // Set payload based on request method type
        if ($requestMethod === RequestMethod::GET) {
            $options['query'] = $payload;
        } elseif ($requestMethod->isFormData()) {
            $options['body'] = $payload;
            $options['headers'] = ['Content-Type' => 'application/x-www-form-urlencoded'];
        } else {
            // JSON is default
            $options['json'] = $payload;
        }

        if ($this->config->authToken !== null) {
            $options['auth_bearer'] = $this->config->authToken;
        }

        try {
            $response = $this->httpClient->request($httpMethod, $url, $options);

            // Materialize the response here so transport-level failures surface as
            // typed exceptions that the retry handler and circuit breaker can act on.
            // getStatusCode() triggers the request and throws on transport errors, but
            // not on 4xx/5xx; getContent(false) reads the body without throwing on those.
            $statusCode = $response->getStatusCode();

            return new N8nHttpResult($statusCode, $response->getContent(false), $response->getHeaders(false));
        } catch (TimeoutExceptionInterface $e) {
            throw new N8nTimeoutException('N8n webhook request timeout', 0, $e);
        } catch (TransportExceptionInterface $e) {
            throw new N8nCommunicationException('Failed to send webhook to N8n: '.$e->getMessage(), 0, $e);
        }
    }

    public function healthCheck(): bool
    {
        if ($this->config->dryRun) {
            return true;
        }

        try {
            $options = ['timeout' => 5];
            if ($this->config->proxy !== null) {
                $options['proxy'] = $this->config->proxy;
            }

            $response = $this->httpClient->request('GET', rtrim($this->config->baseUrl, '/').'/health', $options);

            return $response->getStatusCode() < 400;
        } catch (\Throwable) {
            return false;
        }
    }
}
