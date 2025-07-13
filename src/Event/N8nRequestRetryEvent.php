<?php

declare(strict_types=1);

namespace Freema\N8nBundle\Event;

use Freema\N8nBundle\Domain\N8nRequest;
use Symfony\Contracts\EventDispatcher\Event;

class N8nRequestRetryEvent extends Event
{
    public const NAME = 'n8n.request.retry';

    public function __construct(
        private readonly N8nRequest $request,
        private readonly \Throwable $exception,
        private readonly int $attempt,
        private readonly int $maxAttempts,
        private readonly int $delayMs = 1000,
    ) {
    }

    public function getRequest(): N8nRequest
    {
        return $this->request;
    }

    public function getException(): \Throwable
    {
        return $this->exception;
    }

    public function getAttempt(): int
    {
        return $this->attempt;
    }

    public function getMaxAttempts(): int
    {
        return $this->maxAttempts;
    }

    public function getDelayMs(): int
    {
        return $this->delayMs;
    }
}
