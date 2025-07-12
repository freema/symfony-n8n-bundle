<?php

declare(strict_types=1);

namespace Freema\N8nBundle\Exception;

use Exception;

class N8nException extends Exception
{
    public function __construct(string $message, int $code = 0, ?Exception $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}