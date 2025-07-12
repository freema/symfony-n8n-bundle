<?php

declare(strict_types=1);

namespace Freema\N8nBundle\Contract;

interface N8nResponseHandlerInterface
{
    public function handleN8nResponse(array $responseData, string $requestUuid): void;
    
    public function getHandlerId(): string;
}