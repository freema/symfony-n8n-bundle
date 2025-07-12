<?php

declare(strict_types=1);

namespace Freema\N8nBundle\Contract;

use Freema\N8nBundle\Enum\CommunicationMode;

interface N8nClientInterface
{
    public function send(N8nPayloadInterface $payload, string $workflowId, CommunicationMode $mode = CommunicationMode::FIRE_AND_FORGET): string;
    
    public function sendWithCallback(N8nPayloadInterface $payload, string $workflowId, N8nResponseHandlerInterface $handler): string;
    
    public function sendSync(N8nPayloadInterface $payload, string $workflowId, int $timeoutSeconds = 30): array;
    
    public function getClientId(): string;
    
    public function isHealthy(): bool;
}