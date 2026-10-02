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
        if (!\is_array($bundleData)) {
            $bundleData = [];
        }

        return new self(
            uuid: self::stringOrNull($bundleData, 'uuid') ?? '',
            data: $payload,
            receivedAt: new \DateTimeImmutable(),
            handlerId: self::stringOrNull($bundleData, 'handler_id'),
            clientId: self::stringOrNull($bundleData, 'client_id'),
        );
    }

    /**
     * @param array<mixed> $data
     */
    private static function stringOrNull(array $data, string $key): ?string
    {
        return \is_string($data[$key] ?? null) ? $data[$key] : null;
    }
}
