<?php

declare(strict_types=1);

namespace Freema\N8nBundle\Tests\Fixtures;

use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * The Symfony skeleton default: framework.secret from an empty APP_SECRET.
 */
final class EmptySecretTestKernel extends TestKernel
{
    protected function configureContainer(ContainerBuilder $container, LoaderInterface $loader): void
    {
        parent::configureContainer($container, $loader);

        $container->loadFromExtension('framework', [
            'secret' => '%env(N8N_TEST_APP_SECRET)%',
        ]);
    }

    public function getCacheDir(): string
    {
        return __DIR__.'/../../var/cache/test_empty_secret';
    }
}
