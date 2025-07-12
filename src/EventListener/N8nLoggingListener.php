<?php

declare(strict_types=1);

namespace Freema\N8nBundle\EventListener;

use Freema\N8nBundle\Event\N8nRequestFailedEvent;
use Freema\N8nBundle\Event\N8nRequestSentEvent;
use Freema\N8nBundle\Event\N8nResponseReceivedEvent;
use Freema\N8nBundle\Event\N8nRetryEvent;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

final class N8nLoggingListener implements EventSubscriberInterface
{
    public function __construct(
        private readonly LoggerInterface $logger
    ) {}
    
    public static function getSubscribedEvents(): array
    {
        return [
            N8nRequestSentEvent::NAME => 'onRequestSent',
            N8nResponseReceivedEvent::NAME => 'onResponseReceived',
            N8nRequestFailedEvent::NAME => 'onRequestFailed',
            N8nRetryEvent::NAME => 'onRetry',
        ];
    }
    
    public function onRequestSent(N8nRequestSentEvent $event): void
    {
        $this->logger->info('N8n request sent', [
            'uuid' => $event->request->uuid,
            'workflow_id' => $event->request->workflowId,
            'mode' => $event->request->mode->value,
            'client_id' => $event->request->clientId,
            'http_status' => $event->httpStatusCode
        ]);
    }
    
    public function onResponseReceived(N8nResponseReceivedEvent $event): void
    {
        if ($event->isSuccessful()) {
            $this->logger->info('N8n response received successfully', [
                'uuid' => $event->response->uuid,
                'handler_id' => $event->response->handlerId
            ]);
        } else {
            $this->logger->error('N8n response processing failed', [
                'uuid' => $event->response->uuid,
                'handler_id' => $event->response->handlerId,
                'error' => $event->error?->getMessage()
            ]);
        }
    }
    
    public function onRequestFailed(N8nRequestFailedEvent $event): void
    {
        $this->logger->error('N8n request failed', [
            'uuid' => $event->request->uuid,
            'workflow_id' => $event->request->workflowId,
            'attempt' => $event->attemptNumber,
            'error' => $event->error->getMessage()
        ]);
    }
    
    public function onRetry(N8nRetryEvent $event): void
    {
        $this->logger->warning('N8n request retry', [
            'uuid' => $event->request->uuid,
            'workflow_id' => $event->request->workflowId,
            'attempt' => $event->attemptNumber,
            'max_attempts' => $event->maxAttempts,
            'previous_error' => $event->previousError->getMessage()
        ]);
    }
}