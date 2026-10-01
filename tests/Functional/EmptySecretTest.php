<?php

declare(strict_types=1);

namespace Freema\N8nBundle\Tests\Functional;

use Freema\N8nBundle\Contract\N8nClientInterface;
use Freema\N8nBundle\Tests\Fixtures\CapturingHttpClient;
use Freema\N8nBundle\Tests\Fixtures\EmptySecretTestKernel;
use Freema\N8nBundle\Tests\Fixtures\RecordingResponseHandler;
use Freema\N8nBundle\Tests\Fixtures\RestoresExceptionHandler;
use Freema\N8nBundle\Tests\Fixtures\TestPayload;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Without a secret nothing can be signed: callbacks are refused, but sending
 * without a callback must keep working.
 */
final class EmptySecretTest extends TestCase
{
    use RestoresExceptionHandler;

    private EmptySecretTestKernel $kernel;

    public static function setUpBeforeClass(): void
    {
        (new Filesystem())->remove(__DIR__.'/../../var/cache/test_empty_secret');
    }

    protected function setUp(): void
    {
        $this->rememberExceptionHandler();
        $_SERVER['N8N_TEST_APP_SECRET'] = $_ENV['N8N_TEST_APP_SECRET'] = '';
        CapturingHttpClient::$requests = [];
        $this->kernel = new EmptySecretTestKernel('test', true);
        $this->kernel->boot();
    }

    protected function tearDown(): void
    {
        $this->kernel->shutdown();
        unset($_SERVER['N8N_TEST_APP_SECRET'], $_ENV['N8N_TEST_APP_SECRET']);
        $this->restoreExceptionHandler();
    }

    public function testSendStillWorks(): void
    {
        $response = $this->client()->send(new TestPayload('hello'), 'notify');

        $this->assertTrue($response->isSuccess());
        $this->assertCount(1, CapturingHttpClient::$requests);
    }

    public function testSendWithCallbackRefusesToSendUnsigned(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('callback secret is empty');

        try {
            $this->client()->sendWithCallback(new TestPayload('hello'), 'moderation', new RecordingResponseHandler());
        } finally {
            $this->assertSame([], CapturingHttpClient::$requests, 'nothing may reach n8n');
        }
    }

    public function testCallbacksAreRefused(): void
    {
        $request = Request::create('http://localhost/api/n8n/callback?expires=9999999999&signature=00', 'POST', [], [], [], ['CONTENT_TYPE' => 'application/json'], '{"_n8n_bundle":{"uuid":"0d2b4f6e-8a1c-4e3b-9f7d-5c6a8b9e0f12"}}');

        $this->assertSame(Response::HTTP_UNAUTHORIZED, $this->kernel->handle($request)->getStatusCode());
    }

    private function client(): N8nClientInterface
    {
        /** @var N8nClientInterface $client */
        $client = $this->kernel->getContainer()->get('test.n8n.client');

        return $client;
    }
}
