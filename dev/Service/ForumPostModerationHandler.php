<?php

declare(strict_types=1);

namespace Freema\N8nBundle\Dev\Service;

use Freema\N8nBundle\Contract\N8nResponseHandlerInterface;
use Psr\Log\LoggerInterface;

final class ForumPostModerationHandler implements N8nResponseHandlerInterface
{
    public function __construct(
        private readonly LoggerInterface $logger,
    ) {
    }

    public function handleN8nResponse(array $responseData, string $requestUuid): void
    {
        $this->logger->info('Processing forum post moderation response', [
            'uuid' => $requestUuid,
            'response' => $responseData,
        ]);

        $bundleData = $responseData['_n8n_bundle'] ?? [];
        $context = $bundleData['context'] ?? [];

        if (!isset($context['entity_id'])) {
            $this->logger->error('Missing entity_id in response context');

            return;
        }

        $postId = $context['entity_id'];
        $status = $responseData['status'] ?? 'unknown';
        $spamScore = $responseData['spam_score'] ?? 0;
        $sentiment = $responseData['sentiment'] ?? 'neutral';
        $flags = $responseData['flags'] ?? [];
        $suggestedAction = $responseData['suggested_action'] ?? 'approve';

        $this->logger->info('Forum post moderation result', [
            'post_id' => $postId,
            'status' => $status,
            'spam_score' => $spamScore,
            'sentiment' => $sentiment,
            'flags' => $flags,
            'suggested_action' => $suggestedAction,
        ]);

        switch ($suggestedAction) {
            case 'approve':
                $this->approvePost($postId);
                break;
            case 'manual_review':
                $this->queueForManualReview($postId, $flags);
                break;
            case 'block':
                $this->blockPost($postId, $flags);
                break;
            default:
                $this->logger->warning('Unknown suggested action', ['action' => $suggestedAction]);
        }
    }

    public function getHandlerId(): string
    {
        return 'forum_post_moderation';
    }

    private function approvePost(int $postId): void
    {
        $this->logger->info('Approving forum post', ['post_id' => $postId]);
    }

    private function queueForManualReview(int $postId, array $flags): void
    {
        $this->logger->info('Queueing forum post for manual review', [
            'post_id' => $postId,
            'flags' => $flags,
        ]);
    }

    private function blockPost(int $postId, array $flags): void
    {
        $this->logger->info('Blocking forum post', [
            'post_id' => $postId,
            'flags' => $flags,
        ]);
    }
}
