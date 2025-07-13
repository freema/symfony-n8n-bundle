<?php

declare(strict_types=1);

namespace Freema\N8nBundle\Dev;

use Freema\N8nBundle\N8nBundle;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Bundle\MonologBundle\MonologBundle;
use Symfony\Bundle\TwigBundle\TwigBundle;
use Symfony\Bundle\WebProfilerBundle\WebProfilerBundle;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

class DevKernel extends Kernel
{
    use MicroKernelTrait;

    public function registerBundles(): iterable
    {
        return [
            new FrameworkBundle(),
            new TwigBundle(),
            new MonologBundle(),
            new WebProfilerBundle(),
            new N8nBundle(),
        ];
    }

    protected function configureContainer(ContainerBuilder $container, LoaderInterface $loader): void
    {
        // Framework configuration
        $container->loadFromExtension('framework', [
            'secret' => 'dev-secret-for-n8n-bundle',
            'test' => false,
            'router' => [
                'utf8' => true,
            ],
            'profiler' => [
                'only_exceptions' => false,
            ],
        ]);

        // Monolog configuration
        $container->loadFromExtension('monolog', [
            'handlers' => [
                'main' => [
                    'type' => 'stream',
                    'path' => '%kernel.logs_dir%/%kernel.environment%.log',
                    'level' => 'debug',
                ],
                'console' => [
                    'type' => 'console',
                ],
            ],
        ]);

        // Twig configuration
        $container->loadFromExtension('twig', [
            'default_path' => '%kernel.project_dir%/dev/templates',
        ]);

        // Web profiler configuration
        $container->loadFromExtension('web_profiler', [
            'toolbar' => true,
            'intercept_redirects' => false,
        ]);

        // N8n Bundle configuration
        $container->loadFromExtension('n8n', [
            'clients' => [
                'default' => [
                    'base_url' => $_ENV['N8N_BASE_URL'] ?? 'https://stipek.app.n8n.cloud',
                    'client_id' => $_ENV['N8N_CLIENT_ID'] ?? 'symfony-dev-client',
                    'auth_token' => $_ENV['N8N_AUTH_TOKEN'] ?? null,
                    'timeout_seconds' => 30,
                    'retry_attempts' => 3,
                    'retry_delay_ms' => 1000,
                    'enable_circuit_breaker' => true,
                    'circuit_breaker_threshold' => 5,
                    'circuit_breaker_timeout_seconds' => 60,
                    'dry_run' => (bool) ($_ENV['N8N_DRY_RUN'] ?? false),
                ],
                'test' => [
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
                'log_requests' => true,
            ],
        ]);

        // Create alias for default N8n client interface
        $container->setAlias('Freema\N8nBundle\Contract\N8nClientInterface', 'n8n.client.default');

        // Register services
        $container->autowire('Freema\N8nBundle\Dev\Controller\DemoController')
            ->setAutoconfigured(true)
            ->addTag('controller.service_arguments');

        $container->autowire('Freema\N8nBundle\Dev\Service\ForumPostModerationHandler')
            ->setAutoconfigured(true);

        $container->autowire('Freema\N8nBundle\Dev\Service\ModerationResponseHandler')
            ->setAutoconfigured(true);
    }

    protected function configureRoutes(RoutingConfigurator $routes): void
    {
        // N8n callback route
        $routes->add('n8n_callback', '/api/n8n/callback')
            ->controller('Freema\N8nBundle\Controller\N8nCallbackController::handleCallback')
            ->methods(['POST']);

        // Demo routes
        $routes->add('demo_index', '/')
            ->controller('Freema\N8nBundle\Dev\Controller\DemoController::index')
            ->methods(['GET']);

        $routes->add('demo_fire_and_forget', '/demo/fire-and-forget')
            ->controller('Freema\N8nBundle\Dev\Controller\DemoController::fireAndForget')
            ->methods(['POST']);

        $routes->add('demo_with_callback', '/demo/with-callback')
            ->controller('Freema\N8nBundle\Dev\Controller\DemoController::withCallback')
            ->methods(['POST']);

        $routes->add('demo_sync', '/demo/sync')
            ->controller('Freema\N8nBundle\Dev\Controller\DemoController::sync')
            ->methods(['POST']);

        $routes->add('demo_health', '/demo/health')
            ->controller('Freema\N8nBundle\Dev\Controller\DemoController::health')
            ->methods(['GET']);

        // Web profiler routes
        if ($this->environment === 'dev') {
            $routes->import('@WebProfilerBundle/Resources/config/routing/wdt.xml')->prefix('/_wdt');
            $routes->import('@WebProfilerBundle/Resources/config/routing/profiler.xml')->prefix('/_profiler');
        }
    }

    public function getCacheDir(): string
    {
        return __DIR__.'/cache/'.$this->environment;
    }

    public function getLogDir(): string
    {
        return __DIR__.'/cache/log';
    }
}
