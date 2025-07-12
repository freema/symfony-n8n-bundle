<?php

declare(strict_types=1);

namespace Freema\N8nBundle\Service;

use Freema\N8nBundle\Domain\N8nResponse;
use Freema\N8nBundle\Event\N8nResponseReceivedEvent;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

final class CallbackHandler
{
    public function __construct(
        private readonly RequestTracker $requestTracker,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly LoggerInterface $logger
    ) {}
    
    public function handle(N8nResponse $response): void
    {
        $this->logger->info('Processing N8n callback response', [
            'uuid' => $response->uuid,
            'handler_id' => $response->handlerId
        ]);
        
        $this->eventDispatcher->dispatch(
            new N8nResponseReceivedEvent($response),
            N8nResponseReceivedEvent::NAME
        );
        
        $responseHandler = $this->requestTracker->getResponseHandler($response->uuid);
        
        if ($responseHandler === null) {
            $this->logger->warning('No response handler found for N8n callback', [
                'uuid' => $response->uuid,
                'handler_id' => $response->handlerId
            ]);
            return;
        }
        
        try {
            $responseHandler->handleN8nResponse($response->data, $response->uuid);
            
            $this->logger->info('N8n response handled successfully', [
                'uuid' => $response->uuid,
                'handler_id' => $responseHandler->getHandlerId()
            ]);
            
        } catch (\Throwable $e) {
            $this->logger->error('N8n response handler failed', [
                'uuid' => $response->uuid,
                'handler_id' => $responseHandler->getHandlerId(),
                'error' => $e->getMessage()
            ]);
            
            $this->eventDispatcher->dispatch(
                new N8nResponseReceivedEvent($response, $e),
                N8nResponseReceivedEvent::NAME
            );
        } finally {
            $this->requestTracker->completeRequest($response->uuid);
        }
    }
}