<?php

declare(strict_types=1);

namespace Freema\N8nBundle\Tests\Fixtures;

use Freema\N8nBundle\N8nBundle;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

class TestKernel extends Kernel
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
            'http_client' => [
                'mock_response_factory' => 'test.http_client.mock_response_factory',
            ],
        ]);

        $container->loadFromExtension('n8n', [
            'clients' => [
                'default' => [
                    'base_url' => 'https://test.n8n.cloud',
                    'client_id' => 'test-client',
                    'dry_run' => true,
                    'retry_attempts' => 1,
                    'enable_circuit_breaker' => false,
                ],
            ],
            'callback' => [
                'route_name' => 'n8n_callback',
                'route_path' => '/api/n8n/callback',
            ],
            'debug' => [
                'enabled' => true,
                'log_requests' => false,
            ],
        ]);

        $container->autowire('Freema\N8nBundle\Tests\Fixtures\TestPayload')
            ->setAutoconfigured(true);

        $container->autowire('test.http_client.mock_response_factory')
            ->setClass('Symfony\Component\HttpClient\Response\MockResponse')
            ->setArguments([]);
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
