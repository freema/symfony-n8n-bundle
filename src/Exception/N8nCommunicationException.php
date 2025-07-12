<?php

declare(strict_types=1);

namespace Freema\N8nBundle\Exception;

class N8nCommunicationException extends N8nException
{
    public function __construct(string $message, int $httpCode = 0, ?N8nException $previous = null)
    {
        parent::__construct($message, $httpCode, $previous);
    }
}