<?php

declare(strict_types=1);

namespace Freema\N8nBundle\Testing;

use Freema\N8nBundle\Contract\N8nClientInterface;
use Freema\N8nBundle\Contract\N8nPayloadInterface;
use Freema\N8nBundle\Contract\N8nResponseHandlerInterface;
use Freema\N8nBundle\Dto\N8nResponse;
use Freema\N8nBundle\Enum\CommunicationMode;

/**
 * Mock implementation of N8nClientInterface for testing purposes
 *
 * Allows you to test your application without making actual HTTP requests to n8n.
 * Features:
 * - Record all sent requests for assertions
 * - Configure custom responses
 * - Simulate exceptions
 * - Verify what was sent to n8n
 *
 * @example
 * ```php
 * $mockClient = new MockN8nClient();
 * $mockClient->willReturn(['status' => 'ok', 'score' => 95]);
 *
 * $result = $mockClient->send($payload, 'workflow-id');
 *
 * $mockClient->assertSent('workflow-id');
 * $mockClient->assertSentCount(1);
 * ```
 *
 * @phpstan-type RecordedRequest array{
 *     uuid: string,
 *     workflow_id: string,
 *     payload: N8nPayloadInterface,
 *     mode: CommunicationMode,
 *     method: string,
 *     sent_at: \DateTimeImmutable,
 *     handler?: N8nResponseHandlerInterface,
 *     timeout?: int,
 * }
 */
final class MockN8nClient implements N8nClientInterface
{
    /** @var list<RecordedRequest> */
    private array $sentRequests = [];
    /** @var list<array<mixed>> */
    private array $responseQueue = [];
    private ?\Throwable $exceptionToThrow = null;
    private string $clientId = 'mock-client';
    private bool $isHealthy = true;
    private int $uuidCounter = 0;

    public function send(N8nPayloadInterface $payload, string $workflowId, CommunicationMode $mode = CommunicationMode::FIRE_AND_FORGET): N8nResponse
    {
        if ($this->exceptionToThrow !== null) {
            $exception = $this->exceptionToThrow;
            $this->exceptionToThrow = null;
            throw $exception;
        }

        $uuid = $this->generateUuid();

        $this->recordRequest([
            'uuid' => $uuid,
            'workflow_id' => $workflowId,
            'payload' => $payload,
            'mode' => $mode,
            'method' => 'send',
            'sent_at' => new \DateTimeImmutable(),
        ]);

        $responseData = $this->getNextResponse();

        return new N8nResponse(
            uuid: $uuid,
            response: $responseData,
            mappedResponse: $this->mapResponse($payload, $responseData),
            statusCode: 200,
        );
    }

    public function sendWithCallback(N8nPayloadInterface $payload, string $workflowId, N8nResponseHandlerInterface $handler): string
    {
        if ($this->exceptionToThrow !== null) {
            $exception = $this->exceptionToThrow;
            $this->exceptionToThrow = null;
            throw $exception;
        }

        $uuid = $this->generateUuid();

        $this->recordRequest([
            'uuid' => $uuid,
            'workflow_id' => $workflowId,
            'payload' => $payload,
            'handler' => $handler,
            'mode' => CommunicationMode::ASYNC_WITH_CALLBACK,
            'method' => 'sendWithCallback',
            'sent_at' => new \DateTimeImmutable(),
        ]);

        // Optionally trigger callback immediately in tests
        $responseData = $this->getNextResponse();
        $handler->handleN8nResponse($responseData, $uuid);

        return $uuid;
    }

    public function sendSync(N8nPayloadInterface $payload, string $workflowId, int $timeoutSeconds = 30): N8nResponse
    {
        if ($this->exceptionToThrow !== null) {
            $exception = $this->exceptionToThrow;
            $this->exceptionToThrow = null;
            throw $exception;
        }

        $uuid = $this->generateUuid();

        $this->recordRequest([
            'uuid' => $uuid,
            'workflow_id' => $workflowId,
            'payload' => $payload,
            'timeout' => $timeoutSeconds,
            'mode' => CommunicationMode::SYNC,
            'method' => 'sendSync',
            'sent_at' => new \DateTimeImmutable(),
        ]);

        $responseData = $this->getNextResponse();

        return new N8nResponse(
            uuid: $uuid,
            response: $responseData,
            mappedResponse: $this->mapResponse($payload, $responseData),
            statusCode: 200,
        );
    }

    public function getClientId(): string
    {
        return $this->clientId;
    }

    public function isHealthy(): bool
    {
        return $this->isHealthy;
    }

    // Configuration methods

    /**
     * Set the response data to return for the next request(s)
     * Can be called multiple times to queue different responses
     *
     * @param array<mixed> $response The response data to return
     */
    public function willReturn(array $response): self
    {
        $this->responseQueue[] = $response;

        return $this;
    }

    /**
     * Set multiple responses to be returned in sequence
     *
     * @param array<array<mixed>> $responses Array of response arrays
     */
    public function willReturnSequence(array $responses): self
    {
        foreach ($responses as $response) {
            $this->responseQueue[] = $response;
        }

        return $this;
    }

    /**
     * Configure the client to throw an exception on the next request
     *
     * @param \Throwable $exception The exception to throw
     */
    public function willThrow(\Throwable $exception): self
    {
        $this->exceptionToThrow = $exception;

        return $this;
    }

    /**
     * Set the client ID returned by getClientId()
     *
     * @param string $clientId The client ID
     */
    public function withClientId(string $clientId): self
    {
        $this->clientId = $clientId;

        return $this;
    }

    /**
     * Set the health status returned by isHealthy()
     *
     * @param bool $isHealthy The health status
     */
    public function withHealthStatus(bool $isHealthy): self
    {
        $this->isHealthy = $isHealthy;

        return $this;
    }

    // Assertion methods

    /**
     * Assert that a request was sent to the specified workflow
     *
     * @param string $workflowId The workflow ID to check
     * @param (callable(RecordedRequest): bool)|null $callback Optional callback to further inspect the request
     * @throws \PHPUnit\Framework\AssertionFailedError
     */
    public function assertSent(string $workflowId, ?callable $callback = null): void
    {
        $matchingRequests = $this->findRequests($workflowId, $callback);

        if (empty($matchingRequests)) {
            throw new \PHPUnit\Framework\AssertionFailedError(
                \sprintf('Failed asserting that a request was sent to workflow "%s".', $workflowId),
            );
        }
    }

    /**
     * Assert that no request was sent to the specified workflow
     *
     * @param string $workflowId The workflow ID to check
     * @param (callable(RecordedRequest): bool)|null $callback Optional callback to further inspect requests
     * @throws \PHPUnit\Framework\AssertionFailedError
     */
    public function assertNotSent(string $workflowId, ?callable $callback = null): void
    {
        $matchingRequests = $this->findRequests($workflowId, $callback);

        if (!empty($matchingRequests)) {
            throw new \PHPUnit\Framework\AssertionFailedError(
                \sprintf('Failed asserting that no request was sent to workflow "%s".', $workflowId),
            );
        }
    }

    /**
     * Assert the exact number of requests sent
     *
     * @param int $count Expected count
     * @throws \PHPUnit\Framework\AssertionFailedError
     */
    public function assertSentCount(int $count): void
    {
        $actual = \count($this->sentRequests);

        if ($actual !== $count) {
            throw new \PHPUnit\Framework\AssertionFailedError(
                \sprintf('Failed asserting that exactly %d request(s) were sent. Actually sent %d.', $count, $actual),
            );
        }
    }

    /**
     * Assert that no requests were sent at all
     *
     * @throws \PHPUnit\Framework\AssertionFailedError
     */
    public function assertNothingSent(): void
    {
        $this->assertSentCount(0);
    }

    /**
     * Assert that a request was sent to workflow with specific payload data
     *
     * @param string $workflowId The workflow ID
     * @param array $expectedData Expected payload data (partial match)
     * @throws \PHPUnit\Framework\AssertionFailedError
     */
    public function assertSentWithPayload(string $workflowId, array $expectedData): void
    {
        $this->assertSent($workflowId, static function (array $request) use ($expectedData): bool {
            $actualPayload = $request['payload']->toN8nPayload();

            foreach ($expectedData as $key => $value) {
                if (!isset($actualPayload[$key]) || $actualPayload[$key] !== $value) {
                    return false;
                }
            }

            return true;
        });
    }

    /**
     * Get all recorded requests
     *
     * @return list<RecordedRequest>
     */
    public function getRequests(): array
    {
        return $this->sentRequests;
    }

    /**
     * Get requests sent to a specific workflow
     *
     * @param string $workflowId The workflow ID
     *
     * @return array<int, RecordedRequest>
     */
    public function getRequestsFor(string $workflowId): array
    {
        return array_filter(
            $this->sentRequests,
            static fn (array $request) => $request['workflow_id'] === $workflowId,
        );
    }

    /**
     * Clear all recorded requests and queued responses
     * Useful for resetting state between tests
     */
    public function reset(): self
    {
        $this->sentRequests = [];
        $this->responseQueue = [];
        $this->exceptionToThrow = null;
        $this->uuidCounter = 0;

        return $this;
    }

    // Private helper methods

    /**
     * @param RecordedRequest $request
     */
    private function recordRequest(array $request): void
    {
        $this->sentRequests[] = $request;
    }

    /**
     * @param (callable(RecordedRequest): bool)|null $callback
     *
     * @return array<int, RecordedRequest>
     */
    private function findRequests(string $workflowId, ?callable $callback = null): array
    {
        return array_filter(
            $this->sentRequests,
            static function (array $request) use ($workflowId, $callback) {
                if ($request['workflow_id'] !== $workflowId) {
                    return false;
                }

                if ($callback !== null) {
                    return (bool) $callback($request);
                }

                return true;
            },
        );
    }

    /**
     * @return array<mixed>
     */
    private function getNextResponse(): array
    {
        return array_shift($this->responseQueue) ?? ['status' => 'ok', 'message' => 'Mock response'];
    }

    private function generateUuid(): string
    {
        return \sprintf('mock-uuid-%d', ++$this->uuidCounter);
    }

    private function mapResponse(N8nPayloadInterface $payload, array $responseData): ?object
    {
        $responseClass = $payload->getN8nResponseClass();
        if ($responseClass === null || !class_exists($responseClass)) {
            return null;
        }

        // Simple mapping using constructor
        try {
            $reflection = new \ReflectionClass($responseClass);
            $constructor = $reflection->getConstructor();

            if ($constructor === null) {
                return null;
            }

            $params = [];
            foreach ($constructor->getParameters() as $param) {
                $paramName = $param->getName();
                $params[] = $responseData[$paramName] ?? ($param->isDefaultValueAvailable() ? $param->getDefaultValue() : null);
            }

            return $reflection->newInstanceArgs($params);
        } catch (\Exception $e) {
            return null;
        }
    }
}
