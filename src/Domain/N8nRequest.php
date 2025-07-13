<?php

declare(strict_types=1);

namespace Freema\N8nBundle\Domain;

use Freema\N8nBundle\Contract\N8nPayloadInterface;
use Freema\N8nBundle\Contract\N8nResponseHandlerInterface;
use Freema\N8nBundle\Enum\CommunicationMode;

final readonly class N8nRequest
{
    public function __construct(
        public string $uuid,
        public string $workflowId,
        public N8nPayloadInterface $payload,
        public CommunicationMode $mode,
        public string $clientId,
        public \DateTimeImmutable $createdAt,
        public ?N8nResponseHandlerInterface $responseHandler = null,
        public ?string $callbackUrl = null,
        public ?int $timeoutSeconds = null,
    ) {
    }

    public function toWebhookPayload(): array
    {
        $payload = $this->payload->toN8nPayload();
        $payload['_n8n_bundle'] = [
            'uuid' => $this->uuid,
            'client_id' => $this->clientId,
            'mode' => $this->mode->value,
            'created_at' => $this->createdAt->format(\DATE_ATOM),
            'context' => $this->payload->getN8nContext(),
        ];

        if ($this->callbackUrl !== null) {
            $payload['_n8n_bundle']['callback_url'] = $this->callbackUrl;
        }

        if ($this->responseHandler !== null) {
            $payload['_n8n_bundle']['handler_id'] = $this->responseHandler->getHandlerId();
        }

        return $payload;
    }
}
