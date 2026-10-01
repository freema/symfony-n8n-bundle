<?php

declare(strict_types=1);

namespace Freema\N8nBundle\Service;

use Freema\N8nBundle\Contract\N8nResponseHandlerInterface;

/**
 * Finds a response handler service by its getHandlerId().
 *
 * A callback usually arrives in a different PHP process than the one that
 * called sendWithCallback(), so the handler object passed there is gone.
 * Handlers registered as services (autoconfigured services implementing
 * N8nResponseHandlerInterface are tagged "n8n.response_handler") can be
 * looked up again by their ID.
 */
final class ResponseHandlerRegistry
{
    /** @var array<string, N8nResponseHandlerInterface>|null */
    private ?array $byId = null;

    /**
     * @param iterable<N8nResponseHandlerInterface> $handlers
     */
    public function __construct(
        private readonly iterable $handlers = [],
    ) {
    }

    public function get(string $handlerId): ?N8nResponseHandlerInterface
    {
        if ($this->byId === null) {
            $this->byId = [];
            foreach ($this->handlers as $handler) {
                $this->byId[$handler->getHandlerId()] ??= $handler;
            }
        }

        return $this->byId[$handlerId] ?? null;
    }
}
