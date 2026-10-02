<?php

declare(strict_types=1);

namespace Freema\N8nBundle\Tests\Unit\Debug;

use Freema\N8nBundle\Debug\N8nDataCollector;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

#[CoversClass(N8nDataCollector::class)]
class N8nDataCollectorTest extends TestCase
{
    public function testReportsNothingBeforeCollecting(): void
    {
        $collector = new N8nDataCollector();

        $this->assertSame([], $collector->getRequests());
        $this->assertSame([], $collector->getResponses());
        $this->assertSame([], $collector->getErrors());
        $this->assertSame(0, $collector->getTotalRequests());
        $this->assertSame(0, $collector->getTotalErrors());
        $this->assertSame(0.0, $collector->getTotalTime());
    }

    public function testReportsCollectedRequestsResponsesAndErrors(): void
    {
        $collector = new N8nDataCollector();
        $collector->addRequest('POST', 'https://n8n.example.com/webhook/wf', ['a' => 1], 0.25, 'uuid-1');
        $collector->addRequest('POST', 'https://n8n.example.com/webhook/wf', ['a' => 2], 0.5, 'uuid-2');
        $collector->addResponse('uuid-1', ['ok' => true], 200);
        $collector->addError('uuid-2', 'timeout', new \RuntimeException('timeout'));

        $collector->collect(new Request(), new Response());

        $this->assertCount(2, $collector->getRequests());
        $this->assertArrayHasKey('uuid-1', $collector->getResponses());
        $this->assertArrayHasKey('uuid-2', $collector->getErrors());
        $this->assertSame(2, $collector->getTotalRequests());
        $this->assertSame(1, $collector->getTotalErrors());
        $this->assertSame(0.75, $collector->getTotalTime());
    }
}
