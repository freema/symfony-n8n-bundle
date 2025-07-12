<?php

declare(strict_types=1);

namespace Freema\N8nBundle\Http;

use Freema\N8nBundle\Domain\N8nConfig;
use Freema\N8nBundle\Domain\N8nRequest;
use Freema\N8nBundle\Exception\N8nCommunicationException;
use Freema\N8nBundle\Exception\N8nTimeoutException;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

final class N8nHttpClient
{
    private HttpClientInterface $httpClient;
    
    public function __construct(
        private readonly N8nConfig $config,
        ?HttpClientInterface $httpClient = null
    ) {
        $this->httpClient = $httpClient ?? HttpClient::create([
            'timeout' => $this->config->timeoutSeconds,
            'headers' => array_merge([
                'Content-Type' => 'application/json',
                'User-Agent' => 'Symfony N8n Bundle/1.0'
            ], $this->config->defaultHeaders)
        ]);
    }
    
    public function sendWebhook(N8nRequest $request): ResponseInterface
    {
        if ($this->config->dryRun) {
            return $this->createDryRunResponse($request);
        }
        
        $url = $this->config->getWebhookUrl($request->workflowId);
        $payload = $request->toWebhookPayload();
        
        $options = [
            'json' => $payload,
            'timeout' => $request->timeoutSeconds ?? $this->config->timeoutSeconds
        ];
        
        if ($this->config->authToken !== null) {
            $options['auth_bearer'] = $this->config->authToken;
        }
        
        try {
            return $this->httpClient->request('POST', $url, $options);
        } catch (\Throwable $e) {
            if (str_contains($e->getMessage(), 'timeout')) {
                throw new N8nTimeoutException('N8n webhook request timeout', 0, $e);
            }
            
            throw new N8nCommunicationException(
                'Failed to send webhook to N8n: ' . $e->getMessage(),
                0,
                $e
            );
        }
    }
    
    public function healthCheck(): bool
    {
        if ($this->config->dryRun) {
            return true;
        }
        
        try {
            $response = $this->httpClient->request('GET', $this->config->baseUrl . '/health', [
                'timeout' => 5
            ]);
            
            return $response->getStatusCode() < 400;
        } catch (\Throwable) {
            return false;
        }
    }
    
    private function createDryRunResponse(N8nRequest $request): ResponseInterface
    {
        return new class($request) implements ResponseInterface {
            public function __construct(private N8nRequest $request) {}
            
            public function getStatusCode(): int { return 200; }
            public function getHeaders(bool $throw = true): array { return []; }
            public function getContent(bool $throw = true): string { 
                return json_encode(['dry_run' => true, 'uuid' => $this->request->uuid]); 
            }
            public function toArray(bool $throw = true): array { 
                return ['dry_run' => true, 'uuid' => $this->request->uuid]; 
            }
            public function cancel(): void {}
            public function getInfo(string $type = null): mixed { return null; }
        };
    }
}