<?php

declare(strict_types=1);

namespace Freema\N8nBundle\Tests\Fixtures;

use Freema\N8nBundle\N8nBundle;
use Psr\Log\NullLogger;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

class TestKernel extends Kernel implements CompilerPassInterface
{
    use MicroKernelTrait;

    public function registerBundles(): iterable
    {
        return [
            new FrameworkBundle(),
            new N8nBundle(),
        ];
    }

    protected function configureContainer(ContainerBuilder $container, LoaderInterface $loader): void
    {
        $container->loadFromExtension('framework', [
            'secret' => 'test-secret',
            'test' => true,
            'http_method_override' => false,
            'handle_all_throwables' => true,
        ]);

        $container->loadFromExtension('n8n', [
            'clients' => [
                'default' => [
                    'base_url' => 'https://test.n8n.cloud',
                    'client_id' => 'test-client',
                    'retry_attempts' => 0,
                    'enable_circuit_breaker' => false,
                ],
            ],
            'callback' => [
                'route_name' => 'n8n_callback',
            ],
            'debug' => [
                'enabled' => true,
                'log_requests' => false,
            ],
        ]);

        $container->register('logger', NullLogger::class);
        $container->register(CapturingHttpClient::class)->setPublic(true);
        $container->register(RecordingResponseHandler::class)
            ->setAutoconfigured(true)
            ->setPublic(true);
        $container->setAlias('test.n8n.client', 'n8n.client')->setPublic(true);
    }

    /**
     * Send webhooks through CapturingHttpClient instead of the network.
     */
    public function process(ContainerBuilder $container): void
    {
        $container->getDefinition('n8n.http_client.default')
            ->setArgument(1, new Reference(CapturingHttpClient::class));
    }

    protected function configureRoutes(RoutingConfigurator $routes): void
    {
        $routes->add('n8n_callback', '/api/n8n/callback')
            ->controller('Freema\N8nBundle\Controller\N8nCallbackController::handleCallback')
            ->methods(['POST']);
    }

    public function getCacheDir(): string
    {
        return __DIR__.'/../../var/cache/test';
    }

    public function getLogDir(): string
    {
        return __DIR__.'/../../var/log';
    }
}
