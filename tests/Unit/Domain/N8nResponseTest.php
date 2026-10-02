<?php

declare(strict_types=1);

namespace Freema\N8nBundle\Tests\Unit\Domain;

use Freema\N8nBundle\Domain\N8nResponse;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(N8nResponse::class)]
class N8nResponseTest extends TestCase
{
    public function testReadsTheBundleBlock(): void
    {
        $payload = ['score' => 95, '_n8n_bundle' => ['uuid' => 'uuid-1', 'handler_id' => 'handler', 'client_id' => 'app']];

        $response = N8nResponse::fromWebhookPayload($payload);

        $this->assertSame('uuid-1', $response->uuid);
        $this->assertSame('handler', $response->handlerId);
        $this->assertSame('app', $response->clientId);
        $this->assertSame($payload, $response->data);
    }

    public function testIgnoresValuesThatAreNotStrings(): void
    {
        $response = N8nResponse::fromWebhookPayload(['_n8n_bundle' => ['uuid' => 'uuid-1', 'handler_id' => ['x'], 'client_id' => 42]]);

        $this->assertSame('uuid-1', $response->uuid);
        $this->assertNull($response->handlerId);
        $this->assertNull($response->clientId);
    }

    public function testToleratesAMissingOrMalformedBundleBlock(): void
    {
        $this->assertSame('', N8nResponse::fromWebhookPayload([])->uuid);
        $this->assertSame('', N8nResponse::fromWebhookPayload(['_n8n_bundle' => 'uuid-1'])->uuid);
    }
}
