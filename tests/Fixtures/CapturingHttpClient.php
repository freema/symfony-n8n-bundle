<?php

declare(strict_types=1);

namespace Freema\N8nBundle\Tests\Fixtures;

use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;
use Symfony\Contracts\HttpClient\ResponseStreamInterface;

/**
 * Answers every webhook with 200 and remembers what was sent, the way n8n
 * would receive it.
 */
final class CapturingHttpClient implements HttpClientInterface
{
    /** @var list<array{method: string, url: string, json: mixed}> */
    public static array $requests = [];

    private HttpClientInterface $client;

    public function __construct()
    {
        $this->client = new MockHttpClient(static function (string $method, string $url, array $options): MockResponse {
            self::$requests[] = [
                'method' => $method,
                'url' => $url,
                'json' => isset($options['body']) && \is_string($options['body']) ? json_decode($options['body'], true) : null,
            ];

            return new MockResponse('{"received":true}', ['http_code' => 200]);
        });
    }

    public function request(string $method, string $url, array $options = []): ResponseInterface
    {
        return $this->client->request($method, $url, $options);
    }

    public function stream(ResponseInterface|iterable $responses, ?float $timeout = null): ResponseStreamInterface
    {
        return $this->client->stream($responses, $timeout);
    }

    public function withOptions(array $options): static
    {
        return $this;
    }
}
