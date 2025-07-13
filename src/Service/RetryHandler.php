<?php

declare(strict_types=1);

namespace Freema\N8nBundle\Service;

use Freema\N8nBundle\Domain\N8nRequest;
use Freema\N8nBundle\Event\N8nRequestFailedEvent;
use Freema\N8nBundle\Event\N8nRetryEvent;
use Freema\N8nBundle\Exception\N8nCommunicationException;
use Freema\N8nBundle\Exception\N8nTimeoutException;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

final class RetryHandler
{
    public function __construct(
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly int $maxAttempts = 3,
        private readonly int $delayMs = 1000,
    ) {
    }

    public function executeWithRetry(callable $operation, N8nRequest $request): mixed
    {
        $lastException = null;

        for ($attempt = 1; $attempt <= $this->maxAttempts; ++$attempt) {
            try {
                return $operation();
            } catch (\Throwable $e) {
                $lastException = $e;

                if ($attempt < $this->maxAttempts && $this->isRetryableException($e)) {
                    $this->eventDispatcher->dispatch(
                        new N8nRetryEvent($request, $e, $attempt, $this->maxAttempts),
                        N8nRetryEvent::NAME,
                    );

                    $this->delay($attempt);
                } else {
                    $this->eventDispatcher->dispatch(
                        new N8nRequestFailedEvent($request, $e, $attempt),
                        N8nRequestFailedEvent::NAME,
                    );

                    break;
                }
            }
        }

        if ($lastException !== null) {
            throw $lastException;
        }

        throw new N8nCommunicationException('Retry operation failed without exception');
    }

    private function isRetryableException(\Throwable $e): bool
    {
        return $e instanceof N8nTimeoutException
            || ($e instanceof N8nCommunicationException && $e->getCode() >= 500);
    }

    private function delay(int $attempt): void
    {
        $delayMs = $this->delayMs * 2 ** ($attempt - 1);

        usleep($delayMs * 1000);
    }
}
