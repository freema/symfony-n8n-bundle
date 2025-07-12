<?php

declare(strict_types=1);

namespace Freema\N8nBundle\Dev\Entity;

final readonly class ModerationResponse
{
    public function __construct(
        public bool $allowed,
        public ?string $reason = null,
        public ?string $confidence = null
    ) {}

    public function isAllowed(): bool
    {
        return $this->allowed;
    }

    public function getReason(): ?string
    {
        return $this->reason;
    }

    public function getConfidence(): ?string
    {
        return $this->confidence;
    }

    public function __toString(): string
    {
        $status = $this->allowed ? 'ALLOWED' : 'REJECTED';
        $reason = $this->reason ? " ({$this->reason})" : '';
        return $status . $reason;
    }
}