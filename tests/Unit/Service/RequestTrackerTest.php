<?php

declare(strict_types=1);

namespace Freema\N8nBundle\Tests\Unit\Service;

use Freema\N8nBundle\Domain\N8nRequest;
use Freema\N8nBundle\Enum\CommunicationMode;
use Freema\N8nBundle\Service\RequestTracker;
use Freema\N8nBundle\Service\ResponseHandlerRegistry;
use Freema\N8nBundle\Tests\Fixtures\RecordingResponseHandler;
use Freema\N8nBundle\Tests\Fixtures\TestPayload;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\Filesystem\Filesystem;

final class RequestTrackerTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir().'/n8n-tracker-'.bin2hex(random_bytes(4));
    }

    protected function tearDown(): void
    {
        (new Filesystem())->remove($this->dir);
    }

    public function testCallbackRequestIsFoundByAnotherProcess(): void
    {
        $sender = $this->tracker();
        $receiver = $this->tracker();

        $sender->trackRequest($this->callbackRequest('a7b1c2d3-0000-4000-8000-000000000001'));

        $handler = $receiver->getResponseHandler('a7b1c2d3-0000-4000-8000-000000000001');
        $this->assertInstanceOf(RecordingResponseHandler::class, $handler);
        $this->assertNull($receiver->getRequest('a7b1c2d3-0000-4000-8000-000000000001'), 'the full request stays in the sending process');
    }

    public function testCompletedRequestCannotBeHandledAgain(): void
    {
        $sender = $this->tracker();
        $receiver = $this->tracker();
        $sender->trackRequest($this->callbackRequest('a7b1c2d3-0000-4000-8000-000000000002'));

        $this->assertFalse($receiver->isCompleted('a7b1c2d3-0000-4000-8000-000000000002'));
        $receiver->completeRequest('a7b1c2d3-0000-4000-8000-000000000002');

        $replay = $this->tracker();
        $this->assertTrue($replay->isCompleted('a7b1c2d3-0000-4000-8000-000000000002'));
        $this->assertNull($replay->getResponseHandler('a7b1c2d3-0000-4000-8000-000000000002'));
    }

    public function testUnknownRequestIsMarkedCompletedOnce(): void
    {
        $tracker = $this->tracker();

        $this->assertNull($tracker->getResponseHandler('a7b1c2d3-0000-4000-8000-000000000003'));
        $tracker->completeRequest('a7b1c2d3-0000-4000-8000-000000000003');

        $this->assertTrue($this->tracker()->isCompleted('a7b1c2d3-0000-4000-8000-000000000003'));
    }

    public function testRequestsWithoutCallbackStayInMemory(): void
    {
        $pool = new ArrayAdapter();
        $tracker = new RequestTracker($pool, new ResponseHandlerRegistry([]));

        $tracker->trackRequest($this->callbackRequest('a7b1c2d3-0000-4000-8000-000000000004', CommunicationMode::FIRE_AND_FORGET));
        $tracker->completeRequest('a7b1c2d3-0000-4000-8000-000000000004');

        $this->assertSame([], $pool->getValues());
    }

    public function testHandlerNotRegisteredAsServiceIsNotFound(): void
    {
        $sender = $this->tracker();
        $receiver = new RequestTracker(new FilesystemAdapter('', 0, $this->dir), new ResponseHandlerRegistry([]));

        $sender->trackRequest($this->callbackRequest('a7b1c2d3-0000-4000-8000-000000000005'));

        $this->assertNull($receiver->getResponseHandler('a7b1c2d3-0000-4000-8000-000000000005'));
    }

    public function testKeysTheCachePoolWouldRejectAreIgnored(): void
    {
        $tracker = $this->tracker();

        $this->assertFalse($tracker->isCompleted('{bad}:key'));
        $tracker->completeRequest('{bad}:key');
        $this->assertNull($tracker->getResponseHandler('{bad}:key'));
    }

    public function testWithoutPoolTheTrackerWorksInProcess(): void
    {
        $tracker = new RequestTracker();
        $tracker->trackRequest($this->callbackRequest('a7b1c2d3-0000-4000-8000-000000000006'));

        $this->assertInstanceOf(RecordingResponseHandler::class, $tracker->getResponseHandler('a7b1c2d3-0000-4000-8000-000000000006'));
        $this->assertSame(1, $tracker->getPendingRequestsCount());
    }

    // A new tracker instance on the same pool directory stands for another PHP process.
    private function tracker(): RequestTracker
    {
        return new RequestTracker(
            new FilesystemAdapter('', 0, $this->dir),
            new ResponseHandlerRegistry([new RecordingResponseHandler()]),
            3600,
        );
    }

    private function callbackRequest(string $uuid, CommunicationMode $mode = CommunicationMode::ASYNC_WITH_CALLBACK): N8nRequest
    {
        return new N8nRequest(
            uuid: $uuid,
            workflowId: 'moderation',
            payload: new TestPayload('hello'),
            mode: $mode,
            clientId: 'test-client',
            createdAt: new \DateTimeImmutable(),
            responseHandler: new RecordingResponseHandler(),
        );
    }
}
