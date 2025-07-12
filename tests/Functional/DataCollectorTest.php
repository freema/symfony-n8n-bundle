<?php

declare(strict_types=1);

namespace Freema\N8nBundle\Tests\Functional;

use Freema\N8nBundle\Debug\N8nDataCollector;
use Freema\N8nBundle\Tests\Fixtures\TestKernel;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * @covers \Freema\N8nBundle\Debug\N8nDataCollector
 */
class DataCollectorTest extends TestCase
{
    private TestKernel $kernel;
    private ContainerInterface $container;
    private N8nDataCollector $dataCollector;

    protected function setUp(): void
    {
        $this->kernel = new TestKernel('test', true);
        $this->kernel->boot();
        $this->container = $this->kernel->getContainer();
        $this->dataCollector = $this->container->get('n8n.data_collector');
    }

    protected function tearDown(): void
    {
        $this->kernel->shutdown();
    }

    public function testDataCollectorIsRegistered(): void
    {
        $this->assertInstanceOf(N8nDataCollector::class, $this->dataCollector);
        $this->assertEquals('n8n', $this->dataCollector->getName());
    }

    public function testCollectInitialData(): void
    {
        $request = new Request();
        $response = new Response();

        $this->dataCollector->collect($request, $response);

        $this->assertEquals(0, $this->dataCollector->getTotalRequests());
        $this->assertEquals(0, $this->dataCollector->getTotalErrors());
        $this->assertEquals(0.0, $this->dataCollector->getTotalTime());
        $this->assertEmpty($this->dataCollector->getRequests());
        $this->assertEmpty($this->dataCollector->getResponses());
        $this->assertEmpty($this->dataCollector->getErrors());
    }

    public function testAddRequest(): void
    {
        $this->dataCollector->addRequest('POST', 'https://test.n8n.cloud/webhook/123', ['data' => 'test'], 0.5, 'uuid-123');

        $request = new Request();
        $response = new Response();
        $this->dataCollector->collect($request, $response);

        $this->assertEquals(1, $this->dataCollector->getTotalRequests());
        $this->assertEquals(0.5, $this->dataCollector->getTotalTime());

        $requests = $this->dataCollector->getRequests();
        $this->assertCount(1, $requests);

        $requestData = $requests[0];
        $this->assertEquals('POST', $requestData['method']);
        $this->assertEquals('https://test.n8n.cloud/webhook/123', $requestData['url']);
        $this->assertEquals(['data' => 'test'], $requestData['payload']);
        $this->assertEquals(0.5, $requestData['duration']);
        $this->assertEquals('uuid-123', $requestData['uuid']);
    }

    public function testAddResponse(): void
    {
        $this->dataCollector->addResponse('uuid-123', ['result' => 'success'], 200);

        $request = new Request();
        $response = new Response();
        $this->dataCollector->collect($request, $response);

        $responses = $this->dataCollector->getResponses();
        $this->assertCount(1, $responses);
        $this->assertArrayHasKey('uuid-123', $responses);

        $responseData = $responses['uuid-123'];
        $this->assertEquals(['result' => 'success'], $responseData['response']);
        $this->assertEquals(200, $responseData['status_code']);
    }

    public function testAddError(): void
    {
        $exception = new \RuntimeException('Test error');
        $this->dataCollector->addError('uuid-123', 'Request failed', $exception);

        $request = new Request();
        $response = new Response();
        $this->dataCollector->collect($request, $response);

        $this->assertEquals(1, $this->dataCollector->getTotalErrors());

        $errors = $this->dataCollector->getErrors();
        $this->assertCount(1, $errors);
        $this->assertArrayHasKey('uuid-123', $errors);

        $errorData = $errors['uuid-123'];
        $this->assertEquals('Request failed', $errorData['error']);
        $this->assertSame($exception, $errorData['exception']);
    }

    public function testMultipleRequestsAndResponses(): void
    {
        // Add multiple requests
        $this->dataCollector->addRequest('POST', 'https://test.n8n.cloud/webhook/1', ['data' => 'test1'], 0.3, 'uuid-1');
        $this->dataCollector->addRequest('POST', 'https://test.n8n.cloud/webhook/2', ['data' => 'test2'], 0.7, 'uuid-2');

        // Add responses
        $this->dataCollector->addResponse('uuid-1', ['result' => 'success1'], 200);
        $this->dataCollector->addResponse('uuid-2', ['result' => 'success2'], 201);

        // Add one error
        $this->dataCollector->addError('uuid-3', 'Connection timeout', null);

        $request = new Request();
        $response = new Response();
        $this->dataCollector->collect($request, $response);

        $this->assertEquals(2, $this->dataCollector->getTotalRequests());
        $this->assertEquals(1, $this->dataCollector->getTotalErrors());
        $this->assertEquals(1.0, $this->dataCollector->getTotalTime()); // 0.3 + 0.7

        $this->assertCount(2, $this->dataCollector->getRequests());
        $this->assertCount(2, $this->dataCollector->getResponses());
        $this->assertCount(1, $this->dataCollector->getErrors());
    }

    public function testDataPersistsAcrossCollections(): void
    {
        $this->dataCollector->addRequest('POST', 'https://test.n8n.cloud/webhook/123', ['data' => 'test'], 0.5, 'uuid-123');

        // First collection
        $request1 = new Request();
        $response1 = new Response();
        $this->dataCollector->collect($request1, $response1);

        $this->assertEquals(1, $this->dataCollector->getTotalRequests());

        // Second collection (data should persist)
        $request2 = new Request();
        $response2 = new Response();
        $this->dataCollector->collect($request2, $response2);

        $this->assertEquals(1, $this->dataCollector->getTotalRequests());
        $this->assertCount(1, $this->dataCollector->getRequests());
    }

    public function testResetClearsAllData(): void
    {
        $this->dataCollector->addRequest('POST', 'https://test.n8n.cloud/webhook/123', ['data' => 'test'], 0.5, 'uuid-123');
        $this->dataCollector->addResponse('uuid-123', ['result' => 'success'], 200);
        $this->dataCollector->addError('uuid-456', 'Error message', null);

        $this->dataCollector->reset();

        $request = new Request();
        $response = new Response();
        $this->dataCollector->collect($request, $response);

        $this->assertEquals(0, $this->dataCollector->getTotalRequests());
        $this->assertEquals(0, $this->dataCollector->getTotalErrors());
        $this->assertEquals(0.0, $this->dataCollector->getTotalTime());
        $this->assertEmpty($this->dataCollector->getRequests());
        $this->assertEmpty($this->dataCollector->getResponses());
        $this->assertEmpty($this->dataCollector->getErrors());
    }
}
