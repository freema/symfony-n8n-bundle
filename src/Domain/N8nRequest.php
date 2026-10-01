<?php

declare(strict_types=1);

namespace Freema\N8nBundle\Domain;

use Freema\N8nBundle\Contract\N8nPayloadInterface;
use Freema\N8nBundle\Contract\N8nResponseHandlerInterface;
use Freema\N8nBundle\Enum\CommunicationMode;
use Freema\N8nBundle\Enum\RequestMethod;

final readonly class N8nRequest
{
    public function __construct(
        public string $uuid,
        public string $workflowId,
        public N8nPayloadInterface $payload,
        public CommunicationMode $mode,
        public string $clientId,
        public \DateTimeImmutable $createdAt,
        public RequestMethod $requestMethod = RequestMethod::POST_JSON,
        public ?N8nResponseHandlerInterface $responseHandler = null,
        public ?string $callbackUrl = null,
        public ?int $timeoutSeconds = null,
        public ?int $callbackExpires = null,
        public ?string $callbackSignature = null,
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

        // Also in the body, so a workflow that echoes "_n8n_bundle" back
        // carries the signature even if it posts to a fixed URL.
        if ($this->callbackSignature !== null) {
            $payload['_n8n_bundle']['expires'] = $this->callbackExpires;
            $payload['_n8n_bundle']['signature'] = $this->callbackSignature;
        }

        if ($this->responseHandler !== null) {
            $payload['_n8n_bundle']['handler_id'] = $this->responseHandler->getHandlerId();
        }

        return $payload;
    }
}
