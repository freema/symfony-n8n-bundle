<?php

declare(strict_types=1);

namespace Freema\N8nBundle\Event;

use Freema\N8nBundle\Domain\N8nRequest;
use Symfony\Contracts\EventDispatcher\Event;

final class N8nRetryEvent extends Event
{
    public const NAME = 'n8n.request.retry';
    
    public function __construct(
        public readonly N8nRequest $request,
        public readonly \Throwable $previousError,
        public readonly int $attemptNumber,
        public readonly int $maxAttempts
    ) {}
}