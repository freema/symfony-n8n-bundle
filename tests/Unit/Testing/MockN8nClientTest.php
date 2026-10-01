<?php

declare(strict_types=1);

namespace Freema\N8nBundle\Tests\Unit\Testing;

use Freema\N8nBundle\Contract\N8nResponseHandlerInterface;
use Freema\N8nBundle\Enum\CommunicationMode;
use Freema\N8nBundle\Exception\N8nCommunicationException;
use Freema\N8nBundle\Testing\MockN8nClient;
use Freema\N8nBundle\Tests\Fixtures\TestPayload;
use Freema\N8nBundle\Tests\Fixtures\TestResponse;
use PHPUnit\Framework\AssertionFailedError;
use PHPUnit\Framework\TestCase;

class MockN8nClientTest extends TestCase
{
    private MockN8nClient $client;

    protected function setUp(): void
    {
        $this->client = new MockN8nClient();
    }

    public function testSendReturnsResponse(): void
    {
        $this->client->willReturn(['status' => 'success', 'score' => 95]);

        $payload = new TestPayload('test message');
        $result = $this->client->send($payload, 'workflow-123');

        $this->assertSame('mock-uuid-1', $result->getUuid());
        $this->assertSame(['status' => 'success', 'score' => 95], $result->getResponse());
        $this->assertSame(200, $result->getStatusCode());
        $this->assertTrue($result->isSuccess());
    }

    public function testSendWithCallbackReturnsUuid(): void
    {
        $this->client->willReturn(['result' => 'ok']);

        $payload = new TestPayload('test');
        $handler = new class implements N8nResponseHandlerInterface {
            public array $receivedData = [];
            public string $receivedUuid = '';

            public function handleN8nResponse(array $responseData, string $requestUuid): void
            {
                $this->receivedData = $responseData;
                $this->receivedUuid = $requestUuid;
            }

            public function getHandlerId(): string
            {
                return 'test-handler';
            }
        };

        $uuid = $this->client->sendWithCallback($payload, 'workflow-123', $handler);

        $this->assertSame('mock-uuid-1', $uuid);
        $this->assertSame(['result' => 'ok'], $handler->receivedData);
        $this->assertSame('mock-uuid-1', $handler->receivedUuid);
    }

    public function testSendSyncReturnsResponse(): void
    {
        $this->client->willReturn(['data' => 'sync response']);

        $payload = new TestPayload('sync test');
        $result = $this->client->sendSync($payload, 'workflow-456', 60);

        $this->assertSame('mock-uuid-1', $result->getUuid());
        $this->assertSame(['data' => 'sync response'], $result->getResponse());
    }

    public function testWillReturnSequence(): void
    {
        $this->client->willReturnSequence([
            ['response' => 1],
            ['response' => 2],
            ['response' => 3],
        ]);

        $payload = new TestPayload('test');

        $result1 = $this->client->send($payload, 'workflow-1');
        $result2 = $this->client->send($payload, 'workflow-2');
        $result3 = $this->client->send($payload, 'workflow-3');

        $this->assertSame(['response' => 1], $result1->getResponse());
        $this->assertSame(['response' => 2], $result2->getResponse());
        $this->assertSame(['response' => 3], $result3->getResponse());
    }

    public function testWillThrowException(): void
    {
        $exception = new N8nCommunicationException('Test error', 500);
        $this->client->willThrow($exception);

        $this->expectException(N8nCommunicationException::class);
        $this->expectExceptionMessage('Test error');

        $payload = new TestPayload('test');
        $this->client->send($payload, 'workflow-123');
    }

    public function testAssertSent(): void
    {
        $payload = new TestPayload('test');
        $this->client->send($payload, 'workflow-123');

        $this->client->assertSent('workflow-123');
        $this->expectNotToPerformAssertions(); // If we get here, assertion passed
    }

    public function testAssertSentFails(): void
    {
        $this->expectException(AssertionFailedError::class);
        $this->expectExceptionMessage('Failed asserting that a request was sent to workflow "workflow-123"');

        $this->client->assertSent('workflow-123');
    }

    public function testAssertSentWithCallback(): void
    {
        $payload = new TestPayload('test');
        $this->client->send($payload, 'workflow-123');

        $this->client->assertSent('workflow-123', static function (array $request) {
            return $request['method'] === 'send';
        });

        // Explicit assertion to prevent risky test warning
        $this->assertTrue(true);
    }

    public function testAssertNotSent(): void
    {
        $payload = new TestPayload('test');
        $this->client->send($payload, 'workflow-123');

        $this->client->assertNotSent('workflow-456');
        $this->expectNotToPerformAssertions();
    }

    public function testAssertNotSentFails(): void
    {
        $payload = new TestPayload('test');
        $this->client->send($payload, 'workflow-123');

        $this->expectException(AssertionFailedError::class);
        $this->expectExceptionMessage('Failed asserting that no request was sent to workflow "workflow-123"');

        $this->client->assertNotSent('workflow-123');
    }

    public function testAssertSentCount(): void
    {
        $payload = new TestPayload('test');

        $this->client->send($payload, 'workflow-1');
        $this->client->send($payload, 'workflow-2');
        $this->client->send($payload, 'workflow-3');

        $this->client->assertSentCount(3);
        $this->expectNotToPerformAssertions();
    }

    public function testAssertSentCountFails(): void
    {
        $payload = new TestPayload('test');
        $this->client->send($payload, 'workflow-1');

        $this->expectException(AssertionFailedError::class);
        $this->expectExceptionMessage('Failed asserting that exactly 3 request(s) were sent. Actually sent 1.');

        $this->client->assertSentCount(3);
    }

    public function testAssertNothingSent(): void
    {
        $this->client->assertNothingSent();
        $this->expectNotToPerformAssertions();
    }

    public function testAssertNothingSentFails(): void
    {
        $payload = new TestPayload('test');
        $this->client->send($payload, 'workflow-1');

        $this->expectException(AssertionFailedError::class);

        $this->client->assertNothingSent();
    }

    public function testAssertSentWithPayload(): void
    {
        $payload = new TestPayload('hello world');
        $this->client->send($payload, 'workflow-123');

        $this->client->assertSentWithPayload('workflow-123', [
            'message' => 'hello world',
        ]);
        $this->expectNotToPerformAssertions();
    }

    public function testGetRequests(): void
    {
        $payload1 = new TestPayload('first');
        $payload2 = new TestPayload('second');

        $this->client->send($payload1, 'workflow-1');
        $this->client->send($payload2, 'workflow-2');

        $requests = $this->client->getRequests();

        $this->assertCount(2, $requests);
        $this->assertSame('workflow-1', $requests[0]['workflow_id']);
        $this->assertSame('workflow-2', $requests[1]['workflow_id']);
    }

    public function testGetRequestsFor(): void
    {
        $payload = new TestPayload('test');

        $this->client->send($payload, 'workflow-1');
        $this->client->send($payload, 'workflow-2');
        $this->client->send($payload, 'workflow-1');

        $requests = $this->client->getRequestsFor('workflow-1');

        $this->assertCount(2, $requests);
    }

    public function testReset(): void
    {
        $payload = new TestPayload('test');
        $this->client->willReturn(['data' => 'test']);
        $this->client->send($payload, 'workflow-1');

        $this->assertCount(1, $this->client->getRequests());

        $this->client->reset();

        $this->assertCount(0, $this->client->getRequests());
        $this->client->assertNothingSent();

        // Should use default response after reset
        $result = $this->client->send($payload, 'workflow-2');
        $this->assertSame(['status' => 'ok', 'message' => 'Mock response'], $result->getResponse());
    }

    public function testWithClientId(): void
    {
        $this->client->withClientId('custom-client');

        $this->assertSame('custom-client', $this->client->getClientId());
    }

    public function testWithHealthStatus(): void
    {
        $this->assertTrue($this->client->isHealthy());

        $this->client->withHealthStatus(false);

        $this->assertFalse($this->client->isHealthy());
    }

    public function testDefaultResponseWhenQueueEmpty(): void
    {
        $payload = new TestPayload('test');
        $result = $this->client->send($payload, 'workflow-123');

        $this->assertSame(['status' => 'ok', 'message' => 'Mock response'], $result->getResponse());
    }

    public function testResponseMappingWithClass(): void
    {
        $this->client->willReturn([
            'status' => 'ok',
            'message' => 'looks good',
            'timestamp' => 1234567890,
        ]);

        $payload = new TestPayload('test', TestResponse::class);
        $result = $this->client->send($payload, 'workflow-123');

        $mapped = $result->getMappedResponse();
        $this->assertInstanceOf(TestResponse::class, $mapped);
        $this->assertSame('ok', $mapped->status);
        $this->assertSame('looks good', $mapped->message);
        $this->assertSame(1234567890, $mapped->timestamp);
    }

    public function testUuidIncrementsSequentially(): void
    {
        $payload = new TestPayload('test');

        $result1 = $this->client->send($payload, 'workflow-1');
        $result2 = $this->client->send($payload, 'workflow-2');
        $result3 = $this->client->send($payload, 'workflow-3');

        $this->assertSame('mock-uuid-1', $result1->getUuid());
        $this->assertSame('mock-uuid-2', $result2->getUuid());
        $this->assertSame('mock-uuid-3', $result3->getUuid());
    }

    public function testRecordsCorrectCommunicationMode(): void
    {
        $payload = new TestPayload('test');

        $this->client->send($payload, 'workflow-1', CommunicationMode::FIRE_AND_FORGET);
        $this->client->sendSync($payload, 'workflow-2');

        $requests = $this->client->getRequests();

        $this->assertSame(CommunicationMode::FIRE_AND_FORGET, $requests[0]['mode']);
        $this->assertSame(CommunicationMode::SYNC, $requests[1]['mode']);
    }
}
