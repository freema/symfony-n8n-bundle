<?php

declare(strict_types=1);

namespace Freema\N8nBundle\DependencyInjection;

use Freema\N8nBundle\Domain\N8nConfig;
use Freema\N8nBundle\Service\CircuitBreaker;
use Freema\N8nBundle\Service\RetryHandler;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\DependencyInjection\Reference;

final class N8nExtension extends Extension
{
    public function load(array $configs, ContainerBuilder $container): void
    {
        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        $loader = new YamlFileLoader($container, new FileLocator(__DIR__.'/../Resources/config'));
        $loader->load('services.yaml');

        $this->registerClients($config['clients'], $container);
        $this->registerCallbackConfiguration($config['callback'], $container);
        $this->registerTrackingConfiguration($config['tracking'], $container);
        $this->registerDebugConfiguration($config['debug'], $container);
    }

    private function registerClients(array $clients, ContainerBuilder $container): void
    {
        foreach ($clients as $name => $clientConfig) {
            $configId = sprintf('n8n.config.%s', $name);
            $configDefinition = new Definition(N8nConfig::class, [
                $clientConfig['base_url'],
                $clientConfig['client_id'],
                $clientConfig['auth_token'],
                $clientConfig['timeout_seconds'],
                $clientConfig['retry_attempts'],
                $clientConfig['retry_delay_ms'],
                $clientConfig['enable_circuit_breaker'],
                $clientConfig['circuit_breaker_threshold'],
                $clientConfig['circuit_breaker_timeout_seconds'],
                $clientConfig['dry_run'],
                $clientConfig['default_headers']
            ]);
            $container->setDefinition($configId, $configDefinition);

            $httpClientId = sprintf('n8n.http_client.%s', $name);
            $httpClientDefinition = new Definition();
            $httpClientDefinition->setClass('Freema\N8nBundle\Http\N8nHttpClient');
            $httpClientDefinition->setArguments([
                new Reference($configId),
                null
            ]);
            $container->setDefinition($httpClientId, $httpClientDefinition);

            if ($clientConfig['retry_attempts'] > 0) {
                $retryHandlerId = sprintf('n8n.retry_handler.%s', $name);
                $retryHandlerDefinition = new Definition(RetryHandler::class, [
                    new Reference('event_dispatcher'),
                    $clientConfig['retry_attempts'],
                    $clientConfig['retry_delay_ms']
                ]);
                $container->setDefinition($retryHandlerId, $retryHandlerDefinition);
            }

            if ($clientConfig['enable_circuit_breaker']) {
                $circuitBreakerId = sprintf('n8n.circuit_breaker.%s', $name);
                $circuitBreakerDefinition = new Definition(CircuitBreaker::class, [
                    $clientConfig['circuit_breaker_threshold'],
                    $clientConfig['circuit_breaker_timeout_seconds']
                ]);
                $container->setDefinition($circuitBreakerId, $circuitBreakerDefinition);
            }

            $clientId = sprintf('n8n.client.%s', $name);
            $clientDefinition = new Definition();
            $clientDefinition->setClass('Freema\N8nBundle\Service\N8nClient');
            $clientDefinition->setArguments([
                new Reference($configId),
                new Reference($httpClientId),
                new Reference('n8n.uuid_generator'),
                new Reference('n8n.request_tracker'),
                new Reference('router'),
                new Reference('n8n.response_mapper'),
                isset($retryHandlerId) ? new Reference($retryHandlerId) : null,
                isset($circuitBreakerId) ? new Reference($circuitBreakerId) : null
            ]);
            $container->setDefinition($clientId, $clientDefinition);

            if ($name === 'default') {
                $container->setAlias('n8n.client', $clientId);
            }
        }
    }

    private function registerCallbackConfiguration(array $callbackConfig, ContainerBuilder $container): void
    {
        $container->setParameter('n8n.callback.route_name', $callbackConfig['route_name']);
        $container->setParameter('n8n.callback.route_path', $callbackConfig['route_path']);
    }

    private function registerTrackingConfiguration(array $trackingConfig, ContainerBuilder $container): void
    {
        $container->setParameter('n8n.tracking.cleanup_interval_seconds', $trackingConfig['cleanup_interval_seconds']);
        $container->setParameter('n8n.tracking.max_request_age_seconds', $trackingConfig['max_request_age_seconds']);
    }

    private function registerDebugConfiguration(array $debugConfig, ContainerBuilder $container): void
    {
        // Auto-detect debug mode based on kernel.debug if not explicitly set
        $debugEnabled = $debugConfig['enabled'] ?? '%kernel.debug%';
        
        $container->setParameter('n8n.debug.enabled', $debugEnabled);
        $container->setParameter('n8n.debug.collect_requests', $debugConfig['collect_requests']);
        $container->setParameter('n8n.debug.log_requests', $debugConfig['log_requests']);

        // Register data collector only if debug is enabled and web profiler is available
        if ($debugConfig['collect_requests'] && $container->hasDefinition('profiler')) {
            $dataCollectorDefinition = new Definition('Freema\N8nBundle\Debug\N8nDataCollector');
            $dataCollectorDefinition->addTag('data_collector', [
                'template' => '@N8n/Collector/n8n.html.twig',
                'id' => 'n8n',
                'priority' => 250
            ]);
            $container->setDefinition('n8n.data_collector', $dataCollectorDefinition);
        }
    }
}