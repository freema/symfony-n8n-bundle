<?php

declare(strict_types=1);

namespace Freema\N8nBundle\Tests\Functional;

use Freema\N8nBundle\Contract\N8nClientInterface;
use Freema\N8nBundle\Event\N8nResponseReceivedEvent;
use Freema\N8nBundle\Tests\Fixtures\CapturingHttpClient;
use Freema\N8nBundle\Tests\Fixtures\RecordingResponseHandler;
use Freema\N8nBundle\Tests\Fixtures\RestoresExceptionHandler;
use Freema\N8nBundle\Tests\Fixtures\TestKernel;
use Freema\N8nBundle\Tests\Fixtures\TestPayload;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The callback round trip through real kernels. Each kernel stands for one
 * PHP process: its own container, so its own in-memory state; only the
 * filesystem cache pool is shared, as between PHP-FPM workers.
 */
final class CallbackFlowTest extends TestCase
{
    use RestoresExceptionHandler;

    /** @var list<TestKernel> */
    private array $kernels = [];

    public static function setUpBeforeClass(): void
    {
        // A container compiled from another version of the bundle must not be reused.
        (new Filesystem())->remove(__DIR__.'/../../var/cache/test');
    }

    protected function setUp(): void
    {
        $this->rememberExceptionHandler();
        CapturingHttpClient::$requests = [];
        RecordingResponseHandler::$handled = [];
        $this->process()->getContainer()->get('test.service_container')->get('cache.app')->clear();
    }

    protected function tearDown(): void
    {
        foreach ($this->kernels as $kernel) {
            $kernel->shutdown();
        }
        $this->kernels = [];
        $this->restoreExceptionHandler();
    }

    public function testForgedCallbackIsRejected(): void
    {
        $kernel = $this->process();
        $events = $this->countResponseEvents($kernel);

        $response = $this->postCallback($kernel, 'http://localhost/api/n8n/callback', [
            '_n8n_bundle' => ['uuid' => '6f1c1a4e-0000-4000-8000-000000000000', 'handler_id' => 'recording_handler'],
            'allowed' => true,
        ]);

        $this->assertSame(Response::HTTP_UNAUTHORIZED, $response->getStatusCode());
        $this->assertSame(0, $events->count, 'a forged callback must not dispatch N8nResponseReceivedEvent');
        $this->assertSame([], RecordingResponseHandler::$handled);
    }

    public function testForgedSignatureIsRejected(): void
    {
        [$uuid, $callbackUrl] = $this->sendWithCallback($this->process());
        $forgedUrl = preg_replace('/signature=[0-9a-f]+/', 'signature='.str_repeat('0', 64), $callbackUrl);

        $response = $this->postCallback($this->process(), (string) $forgedUrl, ['_n8n_bundle' => ['uuid' => $uuid]]);

        $this->assertSame(Response::HTTP_UNAUTHORIZED, $response->getStatusCode());
        $this->assertSame([], RecordingResponseHandler::$handled);
    }

    public function testSignatureIsBoundToTheRequest(): void
    {
        [, $callbackUrl] = $this->sendWithCallback($this->process());

        // A valid URL for one request does not authorize a callback for another.
        $response = $this->postCallback($this->process(), $callbackUrl, [
            '_n8n_bundle' => ['uuid' => '6f1c1a4e-0000-4000-8000-000000000000'],
        ]);

        $this->assertSame(Response::HTTP_UNAUTHORIZED, $response->getStatusCode());
    }

    public function testCallbackReachesTheHandlerInAnotherProcess(): void
    {
        [$uuid, $callbackUrl] = $this->sendWithCallback($this->process());

        // n8n posts to the callback URL it was given, echoing "_n8n_bundle".
        $response = $this->postCallback($this->process(), $callbackUrl, [
            '_n8n_bundle' => ['uuid' => $uuid],
            'allowed' => false,
        ]);

        $this->assertSame(Response::HTTP_OK, $response->getStatusCode(), (string) $response->getContent());
        $this->assertCount(1, RecordingResponseHandler::$handled);
        $this->assertSame($uuid, RecordingResponseHandler::$handled[0]['uuid']);
        $this->assertFalse(RecordingResponseHandler::$handled[0]['data']['allowed']);
    }

    public function testSignatureEchoedInTheBodyIsAccepted(): void
    {
        $this->sendWithCallback($this->process());
        $sent = CapturingHttpClient::$requests[0]['json']['_n8n_bundle'];

        // A workflow that posts to a fixed URL but echoes the whole block.
        $response = $this->postCallback($this->process(), 'http://localhost/api/n8n/callback', [
            '_n8n_bundle' => $sent,
            'allowed' => true,
        ]);

        $this->assertSame(Response::HTTP_OK, $response->getStatusCode(), (string) $response->getContent());
        $this->assertCount(1, RecordingResponseHandler::$handled);
    }

    public function testReplayedCallbackIsRejected(): void
    {
        [$uuid, $callbackUrl] = $this->sendWithCallback($this->process());
        $body = ['_n8n_bundle' => ['uuid' => $uuid], 'allowed' => true];

        $first = $this->postCallback($this->process(), $callbackUrl, $body);
        $replay = $this->postCallback($this->process(), $callbackUrl, $body);

        $this->assertSame(Response::HTTP_OK, $first->getStatusCode());
        $this->assertSame(Response::HTTP_CONFLICT, $replay->getStatusCode());
        $this->assertCount(1, RecordingResponseHandler::$handled);
    }

    private function process(): TestKernel
    {
        $kernel = new TestKernel('test', true);
        $kernel->boot();
        $this->kernels[] = $kernel;

        return $kernel;
    }

    /**
     * @return array{string, string} the request UUID and the callback URL n8n received
     */
    private function sendWithCallback(TestKernel $kernel): array
    {
        $container = $kernel->getContainer();
        /** @var N8nClientInterface $client */
        $client = $container->get('test.n8n.client');
        $uuid = $client->sendWithCallback(new TestPayload('Is this post fine?'), 'moderation', $container->get(RecordingResponseHandler::class));

        $sent = end(CapturingHttpClient::$requests);
        $this->assertNotFalse($sent);

        return [$uuid, $sent['json']['_n8n_bundle']['callback_url']];
    }

    /**
     * @param array<mixed> $body
     */
    private function postCallback(TestKernel $kernel, string $url, array $body): Response
    {
        $request = Request::create($url, 'POST', [], [], [], ['CONTENT_TYPE' => 'application/json'], (string) json_encode($body));

        return $kernel->handle($request);
    }

    private function countResponseEvents(TestKernel $kernel): \stdClass
    {
        $counter = new \stdClass();
        $counter->count = 0;
        $kernel->getContainer()->get('event_dispatcher')->addListener(
            N8nResponseReceivedEvent::NAME,
            static function () use ($counter): void {
                ++$counter->count;
            },
        );

        return $counter;
    }
}
