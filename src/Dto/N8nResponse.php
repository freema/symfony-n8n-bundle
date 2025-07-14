<?php

declare(strict_types=1);

namespace Freema\N8nBundle\Dto;

class N8nResponse
{
    public function __construct(
        private string $uuid,
        private array $response,
        private array|object|null $mappedResponse = null,
        private int $statusCode = 200,
    ) {
    }

    public function getUuid(): string
    {
        return $this->uuid;
    }

    public function getResponse(): array
    {
        return $this->response;
    }

    public function getMappedResponse(): object|array|null
    {
        return $this->mappedResponse;
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function isSuccess(): bool
    {
        return $this->statusCode >= 200 && $this->statusCode < 300;
    }

    public function toArray(): array
    {
        return [
            'uuid' => $this->uuid,
            'response' => $this->response,
            'mapped_response' => $this->mappedResponse,
            'status_code' => $this->statusCode,
        ];
    }
}
