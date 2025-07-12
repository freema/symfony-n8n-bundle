<?php

declare(strict_types=1);

namespace Freema\N8nBundle\Contract;

use Freema\N8nBundle\Enum\RequestMethod;

/**
 * Interface for objects that can be sent as payload to n8n workflows
 *
 * Implementing classes define how their data should be serialized for n8n,
 * what HTTP method to use, and optional response handling configuration.
 */
interface N8nPayloadInterface extends N8nResponseMappableInterface
{
    /**
     * Convert the object to array format suitable for n8n workflow consumption
     *
     * @return array The payload data that will be sent to n8n
     */
    public function toN8nPayload(): array;
    
    /**
     * Get contextual information about this payload for tracking and logging
     *
     * @return array Context data including entity type, ID, action, etc.
     */
    public function getN8nContext(): array;

    /**
     * Get the HTTP method and content type for this request
     *
     * @return RequestMethod The HTTP method (POST_FORM, POST_JSON, GET, etc.)
     */
    public function getN8nRequestMethod(): RequestMethod;

    /**
     * Get custom response handler for processing n8n response
     *
     * @return N8nResponseHandlerInterface|null Custom handler or null for default handling
     */
    public function getN8nResponseHandler(): ?N8nResponseHandlerInterface;
}