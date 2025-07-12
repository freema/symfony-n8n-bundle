<?php

declare(strict_types=1);

namespace Freema\N8nBundle\Service;

use Freema\N8nBundle\Exception\N8nCommunicationException;

final class CircuitBreaker
{
    private int $failureCount = 0;
    private ?int $lastFailureTime = null;
    private bool $isOpen = false;
    
    public function __construct(
        private readonly int $threshold = 5,
        private readonly int $timeoutSeconds = 60
    ) {}
    
    public function canExecute(): bool
    {
        if (!$this->isOpen) {
            return true;
        }
        
        if ($this->lastFailureTime === null) {
            return true;
        }
        
        if (time() - $this->lastFailureTime >= $this->timeoutSeconds) {
            $this->reset();
            return true;
        }
        
        return false;
    }
    
    public function recordSuccess(): void
    {
        $this->reset();
    }
    
    public function recordFailure(): void
    {
        $this->failureCount++;
        $this->lastFailureTime = time();
        
        if ($this->failureCount >= $this->threshold) {
            $this->isOpen = true;
        }
    }
    
    public function isOpen(): bool
    {
        return $this->isOpen;
    }
    
    public function getFailureCount(): int
    {
        return $this->failureCount;
    }
    
    public function checkAndThrow(): void
    {
        if (!$this->canExecute()) {
            throw new N8nCommunicationException('Circuit breaker is open - N8n service temporarily unavailable');
        }
    }
    
    private function reset(): void
    {
        $this->failureCount = 0;
        $this->lastFailureTime = null;
        $this->isOpen = false;
    }
}