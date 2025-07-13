<?php

declare(strict_types=1);

namespace Freema\N8nBundle\Service;

use Freema\N8nBundle\Contract\N8nResponseHandlerInterface;
use Freema\N8nBundle\Domain\N8nRequest;

final class RequestTracker
{
    private array $pendingRequests = [];

    public function trackRequest(N8nRequest $request): void
    {
        $this->pendingRequests[$request->uuid] = $request;
    }

    public function getRequest(string $uuid): ?N8nRequest
    {
        return $this->pendingRequests[$uuid] ?? null;
    }

    public function getResponseHandler(string $uuid): ?N8nResponseHandlerInterface
    {
        $request = $this->getRequest($uuid);

        return $request?->responseHandler;
    }

    public function completeRequest(string $uuid): void
    {
        unset($this->pendingRequests[$uuid]);
    }

    public function getPendingRequestsCount(): int
    {
        return \count($this->pendingRequests);
    }

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
}
