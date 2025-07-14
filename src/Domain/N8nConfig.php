<?php

declare(strict_types=1);

namespace Freema\N8nBundle\Domain;

final readonly class N8nConfig
{
    public function __construct(
        public string $baseUrl,
        public string $clientId,
        public ?string $authToken = null,
        public int $timeoutSeconds = 30,
        public int $retryAttempts = 3,
        public int $retryDelayMs = 1000,
        public bool $enableCircuitBreaker = true,
        public int $circuitBreakerThreshold = 5,
        public int $circuitBreakerTimeoutSeconds = 60,
        public bool $dryRun = false,
        public array $defaultHeaders = [],
        public ?string $proxy = null,
    ) {
    }

    public function getWebhookUrl(string $workflowId): string
    {
        return rtrim($this->baseUrl, '/').'/webhook/'.$workflowId;
    }
}
