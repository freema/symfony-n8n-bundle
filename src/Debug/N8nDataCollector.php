<?php

declare(strict_types=1);

namespace Freema\N8nBundle\Debug;

use Symfony\Bundle\FrameworkBundle\DataCollector\AbstractDataCollector;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\VarDumper\Cloner\Data;

class N8nDataCollector extends AbstractDataCollector
{
    private array $requests = [];
    private array $responses = [];
    private array $errors = [];

    public function collect(Request $request, Response $response, \Throwable $exception = null): void
    {
        $this->data = [
            'requests' => $this->requests,
            'responses' => $this->responses,
            'errors' => $this->errors,
            'total_requests' => count($this->requests),
            'total_errors' => count($this->errors),
            'total_time' => array_sum(array_column($this->requests, 'duration')),
        ];
    }

    public function addRequest(string $method, string $url, array $payload, float $duration, string $uuid = null): void
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

    public function addError(string $uuid, string $error, \Throwable $exception = null): void
    {
        $this->errors[$uuid] = [
            'error' => $error,
            'exception' => $exception ? $this->cloneVar($exception) : null,
            'timestamp' => microtime(true),
        ];
    }

    public function getRequests(): array
    {
        return $this->data['requests'] ?? [];
    }

    public function getResponses(): array
    {
        return $this->data['responses'] ?? [];
    }

    public function getErrors(): array
    {
        return $this->data['errors'] ?? [];
    }

    public function getTotalRequests(): int
    {
        return $this->data['total_requests'] ?? 0;
    }

    public function getTotalErrors(): int
    {
        return $this->data['total_errors'] ?? 0;
    }

    public function getTotalTime(): float
    {
        return $this->data['total_time'] ?? 0.0;
    }

    public function getName(): string
    {
        return 'n8n';
    }

    public function reset(): void
    {
        $this->data = [];
        $this->requests = [];
        $this->responses = [];
        $this->errors = [];
    }
}