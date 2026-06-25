<?php

declare(strict_types=1);

namespace Freema\N8nBundle\Tests\Unit\Domain;

use Freema\N8nBundle\Domain\N8nConfig;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Freema\N8nBundle\Domain\N8nConfig
 */
class N8nConfigTest extends TestCase
{
    public function testWebhookUrlUsesProductionPathByDefault(): void
    {
        $config = new N8nConfig(baseUrl: 'https://n8n.example.com', clientId: 'app');

        $this->assertSame('https://n8n.example.com/webhook/wf-123', $config->getWebhookUrl('wf-123'));
    }

    public function testWebhookUrlUsesTestPathWhenEnabled(): void
    {
        $config = new N8nConfig(baseUrl: 'https://n8n.example.com', clientId: 'app', useTestWebhook: true);

        $this->assertSame('https://n8n.example.com/webhook-test/wf-123', $config->getWebhookUrl('wf-123'));
    }

    public function testWebhookUrlTrimsTrailingSlashFromBaseUrl(): void
    {
        $config = new N8nConfig(baseUrl: 'https://n8n.example.com/', clientId: 'app');

        $this->assertSame('https://n8n.example.com/webhook/wf-123', $config->getWebhookUrl('wf-123'));
    }
}
