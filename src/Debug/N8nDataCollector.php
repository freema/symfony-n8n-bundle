<?php

declare(strict_types=1);

namespace Freema\N8nBundle\Debug;

use Symfony\Bundle\FrameworkBundle\DataCollector\AbstractDataCollector;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class N8nDataCollector extends AbstractDataCollector
{
    private array $requests = [];
    private array $responses = [];
    private array $errors = [];

    public function collect(Request $request, Response $response, ?\Throwable $exception = null): void
    {
        $this->data = [
            'requests' => $this->requests,
            'responses' => $this->responses,
            'errors' => $this->errors,
            'total_requests' => \count($this->requests),
            'total_errors' => \count($this->errors),
            'total_time' => array_sum(array_column($this->requests, 'duration')),
        ];
    }

    public function addRequest(string $method, string $url, array $payload, float $duration, ?string $uuid = null): void
    {
        $this->requests[] = [
            'method' => $method,
            'url' => $url,
            'payload' => $this->cloneVar($payload),
            'duration' => $duration,
            'uuid' => $uuid,
            'timestamp' => microtime(true),
        ];
    }

    public function addResponse(string $uuid, array $response, int $statusCode): void
    {
        $this->responses[$uuid] = [
            'response' => $this->cloneVar($response),
            'status_code' => $statusCode,
            'timestamp' => microtime(true),
        ];
    }

    public function addError(string $uuid, string $error, ?\Throwable $exception = null): void
    {
        $this->errors[$uuid] = [
            'error' => $error,
            'exception' => $exception ? $this->cloneVar($exception) : null,
            'timestamp' => microtime(true),
        ];
    }

    public function getRequests(): array
    {
        return $this->collectedArray('requests');
    }

    public function getResponses(): array
    {
        return $this->collectedArray('responses');
    }

    public function getErrors(): array
    {
        return $this->collectedArray('errors');
    }

    public function getTotalRequests(): int
    {
        return $this->collectedInt('total_requests');
    }

    public function getTotalErrors(): int
    {
        return $this->collectedInt('total_errors');
    }

    public function getTotalTime(): float
    {
        $value = $this->data['total_time'] ?? 0.0;

        return is_numeric($value) ? (float) $value : 0.0;
    }

    public function getName(): string
    {
        return 'n8n';
    }

    /**
     * @return array<mixed>
     */
    private function collectedArray(string $key): array
    {
        $value = $this->data[$key] ?? [];

        return \is_array($value) ? $value : [];
    }

    private function collectedInt(string $key): int
    {
        $value = $this->data[$key] ?? 0;

        return \is_int($value) ? $value : 0;
    }

    public function reset(): void
    {
        $this->data = [];
        $this->requests = [];
        $this->responses = [];
        $this->errors = [];
    }
}
