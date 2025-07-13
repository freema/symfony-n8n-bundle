<?php

declare(strict_types=1);

namespace Freema\N8nBundle\Contract;

/**
 * Interface for objects that can specify response mapping configuration
 *
 * Implementing classes can define a target class for automatic mapping
 * of n8n response data to typed PHP objects for better type safety.
 */
interface N8nResponseMappableInterface
{
    /**
     * Get the class name for automatic mapping of n8n response data
     *
     * When specified, the response mapper will attempt to create an instance
     * of the specified class using the response data from n8n.
     *
     * @return string|null The fully qualified class name or null if no mapping needed
     */
    public function getN8nResponseClass(): ?string;
}
