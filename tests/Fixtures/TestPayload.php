<?php

declare(strict_types=1);

namespace Freema\N8nBundle\Tests\Fixtures;

use Freema\N8nBundle\Contract\N8nPayloadInterface;
use Freema\N8nBundle\Contract\N8nResponseMappableInterface;

class TestPayload implements N8nPayloadInterface, N8nResponseMappableInterface
{
    public function __construct(
        private readonly string $message,
        private readonly ?string $responseClass = null,
    ) {
    }

    public function toArray(): array
    {
        return [
            'message' => $this->message,
            'timestamp' => time(),
        ];
    }

    public function getN8nResponseClass(): ?string
    {
        return $this->responseClass;
    }
}
