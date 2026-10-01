<?php

declare(strict_types=1);

namespace Freema\N8nBundle\Tests\Unit\Controller;

use Freema\N8nBundle\Controller\N8nCallbackController;
use Freema\N8nBundle\Service\CallbackHandler;
use Freema\N8nBundle\Service\CallbackSigner;
use Freema\N8nBundle\Service\RequestTracker;
use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class N8nCallbackControllerTest extends TestCase
{
    private const UUID = '0d2b4f6e-8a1c-4e3b-9f7d-5c6a8b9e0f12';

    /** @var list<array{string, string, array<mixed>}> */
    private array $logs = [];

    public function testValidSignatureOnTheUrlIsAccepted(): void
    {
        $signer = new CallbackSigner('secret', 3600);
        $signed = $signer->sign(self::UUID);

        $response = $this->controller($signer)->handleCallback(
            $this->callbackRequest('/n8n/callback?'.http_build_query($signed), ['_n8n_bundle' => ['uuid' => self::UUID]]),
        );

        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
    }

    public function testUnsignedCallbackIsRejected(): void
    {
        $response = $this->controller(new CallbackSigner('secret', 3600))->handleCallback(
            $this->callbackRequest('/n8n/callback', ['_n8n_bundle' => ['uuid' => self::UUID]]),
        );

        $this->assertSame(Response::HTTP_UNAUTHORIZED, $response->getStatusCode());
    }

    public function testWithoutSignerEverythingIsRejected(): void
    {
        $response = $this->controller(null)->handleCallback(
            $this->callbackRequest('/n8n/callback', ['_n8n_bundle' => ['uuid' => self::UUID]]),
        );

        $this->assertSame(Response::HTTP_UNAUTHORIZED, $response->getStatusCode());
    }

    public function testSignatureCheckCanBeTurnedOffForMigration(): void
    {
        $response = $this->controller(new CallbackSigner('secret', 3600), requireSignature: false)->handleCallback(
            $this->callbackRequest('/n8n/callback', ['_n8n_bundle' => ['uuid' => self::UUID]]),
        );

        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
    }

    public function testNonStringUuidIsRejected(): void
    {
        $response = $this->controller(new CallbackSigner('secret', 3600))->handleCallback(
            $this->callbackRequest('/n8n/callback', ['_n8n_bundle' => ['uuid' => ['nested']]]),
        );

        $this->assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
    }

    public function testInvalidBodyIsNotWrittenToTheLog(): void
    {
        $request = Request::create('/n8n/callback', 'POST', [], [], [], [], '{"secret-looking": "token-123"');

        $response = $this->controller(new CallbackSigner('secret', 3600))->handleCallback($request);

        $this->assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
        $this->assertStringNotContainsString('token-123', (string) json_encode($this->logs));
    }

    private function controller(?CallbackSigner $signer, bool $requireSignature = true): N8nCallbackController
    {
        $logger = new class($this->logs) extends AbstractLogger {
            /** @param list<array{string, string, array<mixed>}> $logs */
            public function __construct(private array &$logs)
            {
            }

            public function log($level, string|\Stringable $message, array $context = []): void
            {
                $this->logs[] = [(string) $level, (string) $message, $context];
            }
        };
        $tracker = new RequestTracker();

        return new N8nCallbackController(
            new CallbackHandler($tracker, new EventDispatcher(), $logger),
            $logger,
            $signer,
            $tracker,
            $requireSignature,
        );
    }

    /**
     * @param array<mixed> $body
     */
    private function callbackRequest(string $uri, array $body): Request
    {
        return Request::create($uri, 'POST', [], [], [], ['CONTENT_TYPE' => 'application/json'], (string) json_encode($body));
    }
}
