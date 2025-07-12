<?php

declare(strict_types=1);

namespace Freema\N8nBundle\Contract;

/**
 * Interface for handling responses from n8n workflows
 *
 * Implementing classes define how to process response data received
 * from n8n either via immediate response or callback mechanism.
 */
interface N8nResponseHandlerInterface
{
    /**
     * Process the response data received from n8n workflow
     *
     * @param array $responseData The response data from n8n
     * @param string $requestUuid The UUID of the original request for tracking
     */
    public function handleN8nResponse(array $responseData, string $requestUuid): void;
    
    /**
     * Get unique identifier for this response handler
     *
     * @return string Unique handler identifier for logging and debugging
     */
    public function getHandlerId(): string;
}