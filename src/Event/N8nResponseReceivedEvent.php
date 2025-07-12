<?php

declare(strict_types=1);

namespace Freema\N8nBundle\Event;

use Freema\N8nBundle\Domain\N8nResponse;
use Symfony\Contracts\EventDispatcher\Event;

final class N8nResponseReceivedEvent extends Event
{
    public const NAME = 'n8n.response.received';

    public function __construct(
        public readonly N8nResponse $response,
        public readonly ?\Throwable $error = null,
    ) {
    }

    public function isSuccessful(): bool
    {
        return $this->error === null;
    }
}
