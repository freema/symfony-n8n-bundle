<?php

declare(strict_types=1);

namespace Freema\N8nBundle\Tests\Unit\Http;

use Freema\N8nBundle\Domain\N8nConfig;
use Freema\N8nBundle\Domain\N8nRequest;
use Freema\N8nBundle\Dto\N8nHttpResult;
use Freema\N8nBundle\Enum\CommunicationMode;
use Freema\N8nBundle\Exception\N8nCommunicationException;
use Freema\N8nBundle\Exception\N8nTimeoutException;
use Freema\N8nBundle\Http\N8nHttpClient;
use Freema\N8nBundle\Tests\Fixtures\TestPayload;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\Exception\TimeoutException;
use Symfony\Component\HttpClient\Exception\TransportException;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

/**
 * @covers \Freema\N8nBundle\Http\N8nHttpClient
 */
class N8nHttpClientTest extends TestCase
{
    public function testSendWebhookReturnsMaterializedResultOnSuccess(): void
    {
        $client = new N8nHttpClient(
            $this->config(),
            new MockHttpClient(new MockResponse('{"ok":true}', ['http_code' => 200])),
        );

        $result = $client->sendWebhook($this->request());

        $this->assertInstanceOf(N8nHttpResult::class, $result);
        $this->assertSame(200, $result->statusCode);
        $this->assertTrue($result->isSuccess());
        $this->assertSame(['ok' => true], $result->toArray());
    }

    public function testServerErrorIsReturnedAsResultNotThrown(): void
    {
        $client = new N8nHttpClient(
            $this->config(),
            new MockHttpClient(new MockResponse('boom', ['http_code' => 500])),
        );

        $result = $client->sendWebhook($this->request());

        $this->assertSame(500, $result->statusCode);
        $this->assertFalse($result->isSuccess());
        $this->assertSame('boom', $result->content);
    }

    public function testTransportFailureIsWrappedInCommunicationException(): void
    {
        $client = new N8nHttpClient(
            $this->config(),
            new MockHttpClient(fn () => throw new TransportException('Connection refused')),
        );

        $this->expectException(N8nCommunicationException::class);

        $client->sendWebhook($this->request());
    }

    public function testTimeoutIsWrappedInTimeoutException(): void
    {
        $client = new N8nHttpClient(
            $this->config(),
            new MockHttpClient(fn () => throw new TimeoutException('Idle timeout reached')),
        );

        $this->expectException(N8nTimeoutException::class);

        $client->sendWebhook($this->request());
    }

    public function testDryRunShortCircuitsWithoutHittingTheClient(): void
    {
        $client = new N8nHttpClient(
            $this->config(dryRun: true),
            new MockHttpClient(fn () => throw new \RuntimeException('HTTP client must not be called in dry-run')),
        );

        $result = $client->sendWebhook($this->request());

        $this->assertSame(200, $result->statusCode);
        $this->assertTrue($result->toArray()['dry_run']);
    }

    private function config(bool $dryRun = false): N8nConfig
    {
        return new N8nConfig(baseUrl: 'https://n8n.test', clientId: 'test-client', dryRun: $dryRun);
    }

    private function request(): N8nRequest
    {
        return new N8nRequest(
            uuid: 'uuid-1',
            workflowId: 'wf-1',
            payload: new TestPayload('hello'),
            mode: CommunicationMode::FIRE_AND_FORGET,
            clientId: 'test-client',
            createdAt: new \DateTimeImmutable('2026-01-01T00:00:00+00:00'),
        );
    }
}
