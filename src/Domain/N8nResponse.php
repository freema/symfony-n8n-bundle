<?php

declare(strict_types=1);

namespace Freema\N8nBundle\Domain;

final readonly class N8nResponse
{
    public function __construct(
        public string $uuid,
        public array $data,
        public \DateTimeImmutable $receivedAt,
        public ?string $handlerId = null,
        public ?string $clientId = null,
    ) {
    }

    public static function fromWebhookPayload(array $payload): self
    {
        $bundleData = $payload['_n8n_bundle'] ?? [];

        return new self(
            uuid: $bundleData['uuid'] ?? '',
            data: $payload,
            receivedAt: new \DateTimeImmutable(),
            handlerId: $bundleData['handler_id'] ?? null,
            clientId: $bundleData['client_id'] ?? null,
        );
    }
}
