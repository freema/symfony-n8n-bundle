<?php

declare(strict_types=1);

namespace Freema\N8nBundle\Dev\Entity;

use Freema\N8nBundle\Contract\N8nPayloadInterface;
use Freema\N8nBundle\Contract\N8nResponseHandlerInterface;
use Freema\N8nBundle\Enum\RequestMethod;

final class ForumPost implements N8nPayloadInterface
{
    public function __construct(
        private readonly int $id,
        private readonly string $text,
        private readonly ?string $returnUrl = null,
        private readonly ?N8nResponseHandlerInterface $responseHandler = null,
    ) {
    }

    public function toN8nPayload(): array
    {
        $payload = [
            'text' => $this->text,
        ];

        if ($this->returnUrl !== null) {
            $payload['returnUrl'] = $this->returnUrl;
        }

        return $payload;
    }

    public function getN8nContext(): array
    {
        return [
            'entity_type' => 'forum_post',
            'entity_id' => $this->id,
            'action' => 'moderate',
        ];
    }

    public function getN8nRequestMethod(): RequestMethod
    {
        return RequestMethod::POST_FORM;
    }

    public function getN8nResponseHandler(): ?N8nResponseHandlerInterface
    {
        return $this->responseHandler;
    }

    public function getN8nResponseClass(): ?string
    {
        return ModerationResponse::class;
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getText(): string
    {
        return $this->text;
    }

    public function getReturnUrl(): ?string
    {
        return $this->returnUrl;
    }
}
