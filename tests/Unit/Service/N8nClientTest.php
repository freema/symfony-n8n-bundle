<?php

declare(strict_types=1);

namespace Freema\N8nBundle\Tests\Unit\Service;

use Freema\N8nBundle\Domain\N8nConfig;
use Freema\N8nBundle\Enum\CommunicationMode;
use Freema\N8nBundle\Http\N8nHttpClient;
use Freema\N8nBundle\Service\N8nClient;
use Freema\N8nBundle\Service\RequestTracker;
use Freema\N8nBundle\Service\ResponseMapper;
use Freema\N8nBundle\Service\UuidGenerator;
use Freema\N8nBundle\Tests\Fixtures\TestPayload;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Routing\RouterInterface;

/**
 * @covers \Freema\N8nBundle\Service\N8nClient
 */
class N8nClientTest extends TestCase
{
    private N8nClient $client;
    private MockObject&N8nConfig $config;
    private MockObject&N8nHttpClient $httpClient;
    private MockObject&UuidGenerator $uuidGenerator;
    private MockObject&RequestTracker $requestTracker;
    private MockObject&RouterInterface $router;
    private MockObject&ResponseMapper $responseMapper;
    private MockObject&LoggerInterface $logger;

    protected function setUp(): void
    {
        $this->config = $this->createMock(N8nConfig::class);
        $this->httpClient = $this->createMock(N8nHttpClient::class);
        $this->uuidGenerator = $this->createMock(UuidGenerator::class);
        $this->requestTracker = $this->createMock(RequestTracker::class);
        $this->router = $this->createMock(RouterInterface::class);
        $this->responseMapper = $this->createMock(ResponseMapper::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->client = new N8nClient(
            $this->config,
            $this->httpClient,
            $this->uuidGenerator,
            $this->requestTracker,
            $this->router,
            $this->responseMapper,
            null,
            null,
            $this->logger,
        );
    }

    public function testSendFireAndForgetMode(): void
    {
        $payload = new TestPayload('test message');
        $workflowId = 'workflow-123';
        $uuid = 'uuid-123';

        $this->uuidGenerator->expects($this->once())
            ->method('generate')
            ->willReturn($uuid);

        $this->config->expects($this->once())
            ->method('isDryRun')
            ->willReturn(false);

        $this->httpClient->expects($this->once())
            ->method('sendToWorkflow')
            ->with($workflowId, ['message' => 'test message', 'timestamp' => $this->anything()], $uuid)
            ->willReturn(['status' => 'success', 'data' => ['result' => 'processed']]);

        $result = $this->client->send($payload, $workflowId, CommunicationMode::FIRE_AND_FORGET);

        $this->assertIsArray($result);
        $this->assertEquals($uuid, $result['uuid']);
        $this->assertArrayHasKey('response', $result);
        $this->assertArrayHasKey('status_code', $result);
    }

    public function testSendWithDryRun(): void
    {
        $payload = new TestPayload('test message');
        $workflowId = 'workflow-123';
        $uuid = 'uuid-123';

        $this->uuidGenerator->expects($this->once())
            ->method('generate')
            ->willReturn($uuid);

        $this->config->expects($this->once())
            ->method('isDryRun')
            ->willReturn(true);

        $this->httpClient->expects($this->never())
            ->method('sendToWorkflow');

        $this->logger->expects($this->once())
            ->method('info')
            ->with('DRY RUN: N8n request would be sent', $this->anything());

        $result = $this->client->send($payload, $workflowId);

        $this->assertIsArray($result);
        $this->assertEquals($uuid, $result['uuid']);
        $this->assertEquals(['dry_run' => true], $result['response']);
    }

    public function testSendWithResponseMapping(): void
    {
        $payload = new TestPayload('test message', 'Freema\N8nBundle\Tests\Fixtures\TestResponse');
        $workflowId = 'workflow-123';
        $uuid = 'uuid-123';

        $this->uuidGenerator->expects($this->once())
            ->method('generate')
            ->willReturn($uuid);

        $this->config->expects($this->once())
            ->method('isDryRun')
            ->willReturn(false);

        $responseData = ['status' => 'success', 'message' => 'processed', 'timestamp' => time()];

        $this->httpClient->expects($this->once())
            ->method('sendToWorkflow')
            ->willReturn($responseData);

        $mappedResponse = new \Freema\N8nBundle\Tests\Fixtures\TestResponse('success', 'processed', time());

        $this->responseMapper->expects($this->once())
            ->method('mapToClass')
            ->with($responseData, 'Freema\N8nBundle\Tests\Fixtures\TestResponse')
            ->willReturn($mappedResponse);

        $result = $this->client->send($payload, $workflowId);

        $this->assertArrayHasKey('mapped_response', $result);
        $this->assertSame($mappedResponse, $result['mapped_response']);
    }

    public function testSendAsyncWithCallback(): void
    {
        $payload = new TestPayload('test message');
        $workflowId = 'workflow-123';
        $uuid = 'uuid-123';
        $callbackUrl = 'https://example.com/callback';

        $this->uuidGenerator->expects($this->once())
            ->method('generate')
            ->willReturn($uuid);

        $this->router->expects($this->once())
            ->method('generate')
            ->with('n8n_callback', [], RouterInterface::ABSOLUTE_URL)
            ->willReturn($callbackUrl);

        $this->config->expects($this->once())
            ->method('isDryRun')
            ->willReturn(false);

        $this->requestTracker->expects($this->once())
            ->method('trackRequest')
            ->with($uuid, CommunicationMode::ASYNC_WITH_CALLBACK, $this->anything());

        $this->httpClient->expects($this->once())
            ->method('sendToWorkflow')
            ->with($workflowId, $this->callback(function ($data) use ($callbackUrl) {
                return isset($data['callback_url']) && $data['callback_url'] === $callbackUrl;
            }), $uuid)
            ->willReturn(['status' => 'accepted']);

        $result = $this->client->send($payload, $workflowId, CommunicationMode::ASYNC_WITH_CALLBACK);

        $this->assertEquals($uuid, $result['uuid']);
    }

    public function testGetHealthStatus(): void
    {
        $this->httpClient->expects($this->once())
            ->method('getHealthStatus')
            ->willReturn(['status' => 'healthy', 'timestamp' => time()]);

        $health = $this->client->getHealthStatus();

        $this->assertIsArray($health);
        $this->assertEquals('healthy', $health['status']);
    }
}
