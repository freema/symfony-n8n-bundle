<?php

declare(strict_types=1);

namespace Freema\N8nBundle\Tests\Unit\Service;

use Freema\N8nBundle\Service\CircuitBreaker;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Freema\N8nBundle\Service\CircuitBreaker
 */
class CircuitBreakerTest extends TestCase
{
    private CircuitBreaker $circuitBreaker;

    protected function setUp(): void
    {
        $this->circuitBreaker = new CircuitBreaker(3, 60);
    }

    public function testInitialStateIsClosed(): void
    {
        $this->assertFalse($this->circuitBreaker->isOpen());
    }

    public function testCircuitOpensAfterThresholdFailures(): void
    {
        // Record 3 failures to reach threshold
        $this->circuitBreaker->recordFailure();
        $this->assertFalse($this->circuitBreaker->isOpen());

        $this->circuitBreaker->recordFailure();
        $this->assertFalse($this->circuitBreaker->isOpen());

        $this->circuitBreaker->recordFailure();
        $this->assertTrue($this->circuitBreaker->isOpen());
    }

    public function testCircuitStaysOpenWithinTimeout(): void
    {
        // Open the circuit
        for ($i = 0; $i < 3; ++$i) {
            $this->circuitBreaker->recordFailure();
        }

        $this->assertTrue($this->circuitBreaker->isOpen());

        // Circuit should stay open - success resets it completely
        $this->assertFalse($this->circuitBreaker->canExecute());
    }

    public function testCircuitClosesAfterTimeout(): void
    {
        $circuitBreaker = new CircuitBreaker(2, 1); // 1 second timeout

        // Open the circuit
        $circuitBreaker->recordFailure();
        $circuitBreaker->recordFailure();
        $this->assertTrue($circuitBreaker->isOpen());

        // Wait for timeout to pass
        sleep(2);

        // Circuit should allow execution now
        $this->assertTrue($circuitBreaker->canExecute());
    }

    public function testSuccessResetsFailureCount(): void
    {
        $this->circuitBreaker->recordFailure();
        $this->circuitBreaker->recordFailure();
        $this->assertFalse($this->circuitBreaker->isOpen());

        // Record success should reset failure count
        $this->circuitBreaker->recordSuccess();

        // Now we need 3 more failures to open circuit
        $this->circuitBreaker->recordFailure();
        $this->circuitBreaker->recordFailure();
        $this->assertFalse($this->circuitBreaker->isOpen());

        $this->circuitBreaker->recordFailure();
        $this->assertTrue($this->circuitBreaker->isOpen());
    }

    public function testGetFailureCount(): void
    {
        $this->assertEquals(0, $this->circuitBreaker->getFailureCount());

        $this->circuitBreaker->recordFailure();
        $this->assertEquals(1, $this->circuitBreaker->getFailureCount());

        $this->circuitBreaker->recordFailure();
        $this->assertEquals(2, $this->circuitBreaker->getFailureCount());

        $this->circuitBreaker->recordSuccess();
        $this->assertEquals(0, $this->circuitBreaker->getFailureCount());
    }

    public function testGetLastFailureTime(): void
    {
        $this->assertNull($this->circuitBreaker->getLastFailureTime());

        $before = time();
        $this->circuitBreaker->recordFailure();
        $after = time();

        $lastFailureTime = $this->circuitBreaker->getLastFailureTime();
        $this->assertNotNull($lastFailureTime);
        $this->assertGreaterThanOrEqual($before, $lastFailureTime);
        $this->assertLessThanOrEqual($after, $lastFailureTime);
    }

    public function testZeroThresholdNeverOpens(): void
    {
        $circuitBreaker = new CircuitBreaker(0, 60);

        for ($i = 0; $i < 10; ++$i) {
            $circuitBreaker->recordFailure();
        }

        // With threshold 0, circuit should never open
        $this->assertFalse($circuitBreaker->isOpen());
    }
}
