<?php

declare(strict_types=1);

namespace Freema\N8nBundle\Dev\Entity;

use Freema\N8nBundle\Contract\N8nPayloadInterface;

final class ForumPost implements N8nPayloadInterface
{
    public function __construct(
        private readonly int $id,
        private readonly string $content,
        private readonly int $authorId,
        private readonly \DateTimeImmutable $createdAt,
        private readonly string $threadId
    ) {}

    public function toN8nPayload(): array
    {
        return [
            'text' => $this->content,
            'author_id' => $this->authorId,
            'created_at' => $this->createdAt->format(DATE_ATOM),
            'thread_id' => $this->threadId
        ];
    }

    public function getN8nContext(): array
    {
        return [
            'entity_type' => 'forum_post',
            'entity_id' => $this->id,
            'action' => 'moderate'
        ];
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getContent(): string
    {
        return $this->content;
    }
}