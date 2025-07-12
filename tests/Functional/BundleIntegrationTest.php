<?php

declare(strict_types=1);

namespace Freema\N8nBundle\Tests\Functional;

use Freema\N8nBundle\Contract\N8nClientInterface;
use Freema\N8nBundle\Service\ResponseMapper;
use Freema\N8nBundle\Tests\Fixtures\TestKernel;
use Freema\N8nBundle\Tests\Fixtures\TestPayload;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * @covers \Freema\N8nBundle\N8nBundle
 * @covers \Freema\N8nBundle\DependencyInjection\N8nExtension
 */
class BundleIntegrationTest extends TestCase
{
    private TestKernel $kernel;
    private ContainerInterface $container;

    protected function setUp(): void
    {
        $this->kernel = new TestKernel('test', true);
        $this->kernel->boot();
        $this->container = $this->kernel->getContainer();
    }

    protected function tearDown(): void
    {
        $this->kernel->shutdown();
    }

    public function testBundleLoadsCorrectly(): void
    {
        $bundles = $this->kernel->getBundles();

        $this->assertArrayHasKey('N8nBundle', $bundles);
        $this->assertInstanceOf('Freema\N8nBundle\N8nBundle', $bundles['N8nBundle']);
    }

    public function testN8nClientServiceIsRegistered(): void
    {
        $this->assertTrue($this->container->has('n8n.client.default'));
        $this->assertTrue($this->container->has(N8nClientInterface::class));

        $client = $this->container->get(N8nClientInterface::class);
        $this->assertInstanceOf(N8nClientInterface::class, $client);
    }

    public function testResponseMapperServiceIsRegistered(): void
    {
        $this->assertTrue($this->container->has('n8n.response_mapper'));

        $responseMapper = $this->container->get('n8n.response_mapper');
        $this->assertInstanceOf(ResponseMapper::class, $responseMapper);
    }

    public function testConfigurationIsApplied(): void
    {
        $this->assertTrue($this->container->hasParameter('n8n.debug.enabled'));
        $this->assertTrue($this->container->hasParameter('n8n.debug.log_requests'));
        $this->assertTrue($this->container->hasParameter('n8n.callback.route_name'));
        $this->assertTrue($this->container->hasParameter('n8n.callback.route_path'));

        $this->assertTrue($this->container->getParameter('n8n.debug.enabled'));
        $this->assertEquals('n8n_callback', $this->container->getParameter('n8n.callback.route_name'));
        $this->assertEquals('/api/n8n/callback', $this->container->getParameter('n8n.callback.route_path'));
    }

    public function testN8nClientCanSendRequest(): void
    {
        $client = $this->container->get(N8nClientInterface::class);
        $payload = new TestPayload('integration test message');

        $result = $client->send($payload, 'test-workflow-123');

        $this->assertIsArray($result);
        $this->assertArrayHasKey('uuid', $result);
        $this->assertArrayHasKey('response', $result);
        $this->assertArrayHasKey('status_code', $result);

        // Since we're in dry run mode, response should indicate dry run
        $this->assertEquals(['dry_run' => true], $result['response']);
    }

    public function testHealthStatusEndpoint(): void
    {
        $client = $this->container->get(N8nClientInterface::class);

        $health = $client->getHealthStatus();

        $this->assertIsArray($health);
        $this->assertArrayHasKey('status', $health);
    }

    public function testDataCollectorIsRegistered(): void
    {
        // Data collector should be registered when debug is enabled
        $this->assertTrue($this->container->has('n8n.data_collector'));

        $dataCollector = $this->container->get('n8n.data_collector');
        $this->assertInstanceOf('Freema\N8nBundle\Debug\N8nDataCollector', $dataCollector);
    }

    public function testCircuitBreakerServiceIsRegistered(): void
    {
        // Circuit breaker is optional and depends on configuration
        // In test config, it's disabled, so it shouldn't be registered
        $this->assertFalse($this->container->has('n8n.circuit_breaker.default'));
    }

    public function testRetryHandlerServiceIsRegistered(): void
    {
        // Retry handler should be registered as retry_attempts = 1 in test config
        $this->assertTrue($this->container->has('n8n.retry_handler.default'));

        $retryHandler = $this->container->get('n8n.retry_handler.default');
        $this->assertInstanceOf('Freema\N8nBundle\Service\RetryHandler', $retryHandler);
    }

    public function testUuidGeneratorServiceIsRegistered(): void
    {
        $this->assertTrue($this->container->has('n8n.uuid_generator'));

        $uuidGenerator = $this->container->get('n8n.uuid_generator');
        $this->assertInstanceOf('Freema\N8nBundle\Service\UuidGenerator', $uuidGenerator);

        $uuid = $uuidGenerator->generate();
        $this->assertIsString($uuid);
        $this->assertMatchesRegularExpression('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/', $uuid);
    }

    public function testRequestTrackerServiceIsRegistered(): void
    {
        $this->assertTrue($this->container->has('n8n.request_tracker'));

        $requestTracker = $this->container->get('n8n.request_tracker');
        $this->assertInstanceOf('Freema\N8nBundle\Service\RequestTracker', $requestTracker);
    }
}
