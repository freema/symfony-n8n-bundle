<?php

declare(strict_types=1);

namespace Freema\N8nBundle\Dto;

/**
 * Immutable, fully materialized result of an n8n webhook HTTP exchange.
 *
 * Unlike Symfony's lazy ResponseInterface, the status, body and headers are
 * already resolved when this object is created, so transport-level failures
 * surface at the HTTP layer (where they can be typed and retried) instead of
 * leaking out later when the response is first accessed.
 */
final readonly class N8nHttpResult
{
    /**
     * @param array<string, list<string>> $headers
     */
    public function __construct(
        public int $statusCode,
        public string $content,
        public array $headers = [],
    ) {
    }

    public function isSuccess(): bool
    {
        return $this->statusCode >= 200 && $this->statusCode < 300;
    }

    /**
     * @return array<mixed>
     */
    public function toArray(): array
    {
        $data = json_decode($this->content, true);

        return \is_array($data) ? $data : [];
    }
}
