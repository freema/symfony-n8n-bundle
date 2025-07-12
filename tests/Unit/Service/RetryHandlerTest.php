<?php

declare(strict_types=1);

namespace Freema\N8nBundle\Tests\Unit\Service;

use Freema\N8nBundle\Event\N8nRequestRetryEvent;
use Freema\N8nBundle\Service\RetryHandler;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * @covers \Freema\N8nBundle\Service\RetryHandler
 */
class RetryHandlerTest extends TestCase
{
    private RetryHandler $retryHandler;
    private MockObject&EventDispatcherInterface $eventDispatcher;

    protected function setUp(): void
    {
        $this->eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $this->retryHandler = new RetryHandler($this->eventDispatcher, 3, 1000);
    }

    public function testExecuteSucceedsOnFirstAttempt(): void
    {
        $callable = function () {
            return 'success';
        };

        $this->eventDispatcher->expects($this->never())
            ->method('dispatch');

        $result = $this->retryHandler->execute($callable);

        $this->assertEquals('success', $result);
    }

    public function testExecuteRetriesOnException(): void
    {
        $attempts = 0;
        $callable = function () use (&$attempts) {
            ++$attempts;
            if ($attempts < 3) {
                throw new \RuntimeException('Temporary failure');
            }

            return 'success';
        };

        $this->eventDispatcher->expects($this->exactly(2))
            ->method('dispatch')
            ->with($this->isInstanceOf(N8nRequestRetryEvent::class));

        $result = $this->retryHandler->execute($callable);

        $this->assertEquals('success', $result);
        $this->assertEquals(3, $attempts);
    }

    public function testExecuteFailsAfterMaxAttempts(): void
    {
        $attempts = 0;
        $callable = function () use (&$attempts): void {
            ++$attempts;
            throw new \RuntimeException("Failure attempt {$attempts}");
        };

        $this->eventDispatcher->expects($this->exactly(3))
            ->method('dispatch')
            ->with($this->isInstanceOf(N8nRequestRetryEvent::class));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Failure attempt 4');

        $this->retryHandler->execute($callable);
    }

    public function testExecuteWithCustomRetryDecision(): void
    {
        $attempts = 0;
        $callable = function () use (&$attempts): void {
            ++$attempts;
            throw new \RuntimeException('Always fail');
        };

        // Custom retry decision that never retries
        $shouldRetry = function (\Throwable $exception, int $attempt): bool {
            return false;
        };

        $this->eventDispatcher->expects($this->never())
            ->method('dispatch');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Always fail');

        $this->retryHandler->execute($callable, $shouldRetry);

        $this->assertEquals(1, $attempts);
    }

    public function testExecuteWithSpecificExceptionRetry(): void
    {
        $attempts = 0;
        $callable = function () use (&$attempts) {
            ++$attempts;
            if ($attempts === 1) {
                throw new \RuntimeException('Retryable');
            } elseif ($attempts === 2) {
                throw new \InvalidArgumentException('Not retryable');
            }

            return 'success';
        };

        // Only retry RuntimeException
        $shouldRetry = function (\Throwable $exception, int $attempt): bool {
            return $exception instanceof \RuntimeException && $attempt < 3;
        };

        $this->eventDispatcher->expects($this->once())
            ->method('dispatch')
            ->with($this->isInstanceOf(N8nRequestRetryEvent::class));

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Not retryable');

        $this->retryHandler->execute($callable, $shouldRetry);
    }

    public function testEventContainsCorrectInformation(): void
    {
        $exception = new \RuntimeException('Test exception');
        $callable = function () use ($exception): void {
            throw $exception;
        };

        $this->eventDispatcher->expects($this->exactly(3))
            ->method('dispatch')
            ->with($this->callback(function (N8nRequestRetryEvent $event) use ($exception) {
                $this->assertSame($exception, $event->getException());
                $this->assertGreaterThan(0, $event->getAttempt());
                $this->assertLessThanOrEqual(3, $event->getAttempt());
                $this->assertGreaterThanOrEqual(1000, $event->getDelayMs());

                return true;
            }));

        $this->expectException(\RuntimeException::class);

        $this->retryHandler->execute($callable);
    }

    public function testZeroMaxAttemptsExecutesOnce(): void
    {
        $retryHandler = new RetryHandler($this->eventDispatcher, 0, 1000);

        $attempts = 0;
        $callable = function () use (&$attempts): void {
            ++$attempts;
            throw new \RuntimeException('Always fail');
        };

        $this->eventDispatcher->expects($this->never())
            ->method('dispatch');

        $this->expectException(\RuntimeException::class);

        $retryHandler->execute($callable);

        $this->assertEquals(1, $attempts);
    }
}
