<?php

declare(strict_types=1);

namespace Freema\N8nBundle\Contract;

use Freema\N8nBundle\Enum\CommunicationMode;

/**
 * Main interface for communicating with n8n workflow automation platform
 *
 * Provides three communication modes:
 * - Fire & Forget: Send data and get immediate response
 * - Async with Callback: Send data and receive response via callback URL
 * - Synchronous: Send data and wait for immediate response
 */
interface N8nClientInterface
{
    /**
     * Send payload to n8n workflow and return immediate response
     *
     * @param N8nPayloadInterface $payload The data to send to n8n
     * @param string $workflowId The n8n workflow/webhook identifier
     * @param CommunicationMode $mode The communication mode to use
     * @return array Response data containing uuid, response, mapped_response, and status_code
     */
    public function send(N8nPayloadInterface $payload, string $workflowId, CommunicationMode $mode = CommunicationMode::FIRE_AND_FORGET): array;
    
    /**
     * Send payload to n8n workflow with callback handler for async processing
     *
     * @param N8nPayloadInterface $payload The data to send to n8n
     * @param string $workflowId The n8n workflow/webhook identifier
     * @param N8nResponseHandlerInterface $handler Handler to process the callback response
     * @return string The UUID of the request for tracking
     */
    public function sendWithCallback(N8nPayloadInterface $payload, string $workflowId, N8nResponseHandlerInterface $handler): string;
    
    /**
     * Send payload to n8n workflow synchronously and wait for response
     *
     * @param N8nPayloadInterface $payload The data to send to n8n
     * @param string $workflowId The n8n workflow/webhook identifier
     * @param int $timeoutSeconds Maximum time to wait for response
     * @return array The response data from n8n
     */
    public function sendSync(N8nPayloadInterface $payload, string $workflowId, int $timeoutSeconds = 30): array;
    
    /**
     * Get the client identifier for this n8n client instance
     *
     * @return string The client ID configured for this instance
     */
    public function getClientId(): string;
    
    /**
     * Check if the n8n connection is healthy and responsive
     *
     * @return bool True if the connection is healthy, false otherwise
     */
    public function isHealthy(): bool;
}