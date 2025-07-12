<?php

declare(strict_types=1);

namespace Freema\N8nBundle\Contract;

interface N8nPayloadInterface
{
    public function toN8nPayload(): array;
    
    public function getN8nContext(): array;
}