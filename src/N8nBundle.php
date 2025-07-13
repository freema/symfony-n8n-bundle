<?php

declare(strict_types=1);

namespace Freema\N8nBundle;

use Freema\N8nBundle\DependencyInjection\N8nExtension;
use Symfony\Component\HttpKernel\Bundle\Bundle;

final class N8nBundle extends Bundle
{
    public function getContainerExtension(): N8nExtension
    {
        return new N8nExtension();
    }
}
