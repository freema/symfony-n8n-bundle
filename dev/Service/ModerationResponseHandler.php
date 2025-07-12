<?php

declare(strict_types=1);

namespace Freema\N8nBundle\Dev\Service;

use Freema\N8nBundle\Contract\N8nResponseHandlerInterface;
use Psr\Log\LoggerInterface;

final class ModerationResponseHandler implements N8nResponseHandlerInterface
{
    public function __construct(
        private readonly LoggerInterface $logger
    ) {}

    public function handleN8nResponse(array $responseData, string $requestUuid): void
    {
        $this->logger->info('Moderation response received', [
            'uuid' => $requestUuid,
            'response' => $responseData
        ]);

        // Handle the specific response format: {"allowed": false}
        $allowed = $responseData['allowed'] ?? null;
        
        if ($allowed === true) {
            $this->handleContentApproved($requestUuid, $responseData);
        } elseif ($allowed === false) {
            $this->handleContentRejected($requestUuid, $responseData);
        } else {
            $this->handleUnknownResponse($requestUuid, $responseData);
        }
    }

    public function getHandlerId(): string
    {
        return 'forum_post_moderation';
    }

    private function handleContentApproved(string $uuid, array $responseData): void
    {
        $this->logger->info('Content approved by moderation', [
            'uuid' => $uuid,
            'action' => 'approve'
        ]);
        
        // Here you would typically:
        // - Update database to mark content as approved
        // - Send notification to user
        // - Publish content if it was held for review
        
        // Remove echo - use only logging in production
        // echo "✅ Content {$uuid} was APPROVED\n";
    }

    private function handleContentRejected(string $uuid, array $responseData): void
    {
        $this->logger->warning('Content rejected by moderation', [
            'uuid' => $uuid,
            'action' => 'reject',
            'reason' => $responseData['reason'] ?? 'No reason provided'
        ]);
        
        // Here you would typically:
        // - Update database to mark content as rejected
        // - Send notification to user with reason
        // - Move content to moderation queue for human review
        
        // Remove echo - use only logging in production
        // $reason = $responseData['reason'] ?? 'Content violates community guidelines';
        // echo "❌ Content {$uuid} was REJECTED: {$reason}\n";
    }

    private function handleUnknownResponse(string $uuid, array $responseData): void
    {
        $this->logger->error('Unknown moderation response format', [
            'uuid' => $uuid,
            'response' => $responseData
        ]);
        
        // Remove echo - use only logging in production
        // echo "⚠️ Content {$uuid} received unknown response format\n";
    }
}