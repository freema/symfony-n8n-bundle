<?php

declare(strict_types=1);

namespace Freema\N8nBundle\Service;

use Symfony\Component\Uid\Uuid;

final class UuidGenerator
{
    public function generate(): string
    {
        return Uuid::v4()->toRfc4122();
    }
}
