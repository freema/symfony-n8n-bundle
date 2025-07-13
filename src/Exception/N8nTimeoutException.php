<?php

declare(strict_types=1);

namespace Freema\N8nBundle\Exception;

class N8nTimeoutException extends N8nCommunicationException
{
    public function __construct(string $message = 'N8n request timeout', int $code = 0, ?N8nException $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
