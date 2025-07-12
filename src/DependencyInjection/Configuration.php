<?php

declare(strict_types=1);

namespace Freema\N8nBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

final class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('n8n');
        $rootNode = $treeBuilder->getRootNode();

        $rootNode
            ->children()
                ->arrayNode('clients')
                    ->useAttributeAsKey('name')
                    ->prototype('array')
                        ->children()
                            ->scalarNode('base_url')
                                ->isRequired()
                                ->cannotBeEmpty()
                            ->end()
                            ->scalarNode('client_id')
                                ->isRequired()
                                ->cannotBeEmpty()
                            ->end()
                            ->scalarNode('auth_token')
                                ->defaultNull()
                            ->end()
                            ->integerNode('timeout_seconds')
                                ->defaultValue(30)
                                ->min(1)
                            ->end()
                            ->integerNode('retry_attempts')
                                ->defaultValue(3)
                                ->min(0)
                            ->end()
                            ->integerNode('retry_delay_ms')
                                ->defaultValue(1000)
                                ->min(0)
                            ->end()
                            ->booleanNode('enable_circuit_breaker')
                                ->defaultTrue()
                            ->end()
                            ->integerNode('circuit_breaker_threshold')
                                ->defaultValue(5)
                                ->min(1)
                            ->end()
                            ->integerNode('circuit_breaker_timeout_seconds')
                                ->defaultValue(60)
                                ->min(1)
                            ->end()
                            ->booleanNode('dry_run')
                                ->defaultFalse()
                            ->end()
                            ->arrayNode('default_headers')
                                ->useAttributeAsKey('name')
                                ->prototype('scalar')->end()
                            ->end()
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('callback')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('route_name')
                            ->defaultValue('n8n_callback')
                        ->end()
                        ->scalarNode('route_path')
                            ->defaultValue('/n8n/callback')
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('tracking')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->integerNode('cleanup_interval_seconds')
                            ->defaultValue(3600)
                            ->min(60)
                        ->end()
                        ->integerNode('max_request_age_seconds')
                            ->defaultValue(86400)
                            ->min(300)
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('debug')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->booleanNode('enabled')
                            ->defaultNull()
                            ->info('Enable debug mode. When null, auto-detects based on kernel.debug parameter')
                        ->end()
                        ->booleanNode('log_requests')
                            ->defaultTrue()
                            ->info('Log N8n requests to configured logger')
                        ->end()
                    ->end()
                ->end()
            ->end();

        return $treeBuilder;
    }
}
