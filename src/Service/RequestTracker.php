<?php

declare(strict_types=1);

namespace Freema\N8nBundle\Service;

use Freema\N8nBundle\Contract\N8nResponseHandlerInterface;
use Freema\N8nBundle\Domain\N8nRequest;
use Freema\N8nBundle\Enum\CommunicationMode;
use Psr\Cache\CacheItemPoolInterface;

/**
 * Pairs n8n callbacks with the requests that asked for them.
 *
 * Requests are kept in process memory, and requests sent with a callback are
 * also written to a cache pool: the callback normally arrives in another PHP
 * process (another PHP-FPM worker, another server), which only finds the
 * request there. The pool holds a small array (state, handler ID, workflow,
 * client, timestamps), never the payload or the handler object; the handler
 * is looked up again through the ResponseHandlerRegistry. A handled request
 * stays in the pool as "completed" until it expires, so a replayed callback
 * can be refused.
 */
final class RequestTracker
{
    private const KEY_PREFIX = 'n8n_bundle.request.';
    private const STATE_PENDING = 'pending';
    private const STATE_COMPLETED = 'completed';

    /** @var array<string, N8nRequest> */
    private array $pendingRequests = [];

    public function __construct(
        private readonly ?CacheItemPoolInterface $store = null,
        private readonly ?ResponseHandlerRegistry $handlers = null,
        private readonly int $ttlSeconds = 86400,
    ) {
    }

    public function trackRequest(N8nRequest $request): void
    {
        $this->pendingRequests[$request->uuid] = $request;

        if ($request->mode === CommunicationMode::ASYNC_WITH_CALLBACK) {
            $this->save($request->uuid, [
                'state' => self::STATE_PENDING,
                'handler_id' => $request->responseHandler?->getHandlerId(),
                'workflow_id' => $request->workflowId,
                'client_id' => $request->clientId,
                'created_at' => $request->createdAt->getTimestamp(),
            ], $request->createdAt->getTimestamp() + $this->ttlSeconds);
        }
    }

    /**
     * The full request, only within the process that sent it.
     */
    public function getRequest(string $uuid): ?N8nRequest
    {
        return $this->pendingRequests[$uuid] ?? null;
    }

    public function getResponseHandler(string $uuid): ?N8nResponseHandlerInterface
    {
        $request = $this->getRequest($uuid);
        if ($request !== null) {
            return $request->responseHandler;
        }

        $record = $this->load($uuid);
        if ($record === null || $record['state'] !== self::STATE_PENDING || !\is_string($record['handler_id'] ?? null)) {
            return null;
        }

        return $this->handlers?->get($record['handler_id']);
    }

    /**
     * Whether a callback for this request was already handled.
     */
    public function isCompleted(string $uuid): bool
    {
        if (isset($this->pendingRequests[$uuid])) {
            return false;
        }

        return ($this->load($uuid)['state'] ?? null) === self::STATE_COMPLETED;
    }

    public function completeRequest(string $uuid): void
    {
        $request = $this->pendingRequests[$uuid] ?? null;
        unset($this->pendingRequests[$uuid]);

        // Requests without a callback never reach the pool.
        if ($request !== null && $request->mode !== CommunicationMode::ASYNC_WITH_CALLBACK) {
            return;
        }

        $record = $this->load($uuid) ?? [];
        $createdAt = \is_int($record['created_at'] ?? null) ? $record['created_at'] : time();
        $record['state'] = self::STATE_COMPLETED;

        $this->save($uuid, $record, $createdAt + $this->ttlSeconds);
    }

    /**
     * Requests tracked in this process.
     */
    public function getPendingRequestsCount(): int
    {
        return \count($this->pendingRequests);
    }

    /**
     * Drops old requests tracked in this process. Entries in the cache pool
     * expire on their own after the tracking TTL.
     */
    public function clearExpiredRequests(int $maxAgeSeconds = 3600): int
    {
        $expiredCount = 0;
        $cutoff = time() - $maxAgeSeconds;

        foreach ($this->pendingRequests as $uuid => $request) {
            if ($request->createdAt->getTimestamp() < $cutoff) {
                unset($this->pendingRequests[$uuid]);
                ++$expiredCount;
            }
        }

        return $expiredCount;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function load(string $uuid): ?array
    {
        if ($this->store === null || !self::isValidKey($uuid)) {
            return null;
        }

        $item = $this->store->getItem(self::KEY_PREFIX.$uuid);
        $record = $item->isHit() ? $item->get() : null;

        return \is_array($record) && \is_string($record['state'] ?? null) ? $record : null;
    }

    /**
     * @param array<string, mixed> $record
     */
    private function save(string $uuid, array $record, int $expiresAt): void
    {
        if ($this->store === null || !self::isValidKey($uuid)) {
            return;
        }

        $item = $this->store->getItem(self::KEY_PREFIX.$uuid);
        $item->set($record);
        $item->expiresAt((new \DateTimeImmutable())->setTimestamp($expiresAt));
        $this->store->save($item);
    }

    // UUIDs come from the bundle; anything else is never a key worth asking
    // the pool for (PSR-6 reserves {}()/\@: in keys).
    private static function isValidKey(string $uuid): bool
    {
        return preg_match('/^[A-Za-z0-9_.-]{1,128}$/', $uuid) === 1;
    }
}
