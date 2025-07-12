<?php

declare(strict_types=1);

namespace Freema\N8nBundle\Tests\Fixtures;

use Freema\N8nBundle\Contract\N8nPayloadInterface;
use Freema\N8nBundle\Contract\N8nResponseHandlerInterface;
use Freema\N8nBundle\Enum\RequestMethod;

class TestPayload implements N8nPayloadInterface
{
    public function __construct(
        private readonly string $message,
        private readonly ?string $responseClass = null,
    ) {
    }

    public function toN8nPayload(): array
    {
        return [
            'message' => $this->message,
            'timestamp' => time(),
        ];
    }

    public function getN8nContext(): array
    {
        return [
            'entity_type' => 'test',
            'action' => 'test_message',
            'message' => $this->message,
        ];
    }

    public function getN8nRequestMethod(): RequestMethod
    {
        return RequestMethod::POST_JSON;
    }

    public function getN8nResponseHandler(): ?N8nResponseHandlerInterface
    {
        return null;
    }

    public function getN8nResponseClass(): ?string
    {
        return $this->responseClass;
    }
}
