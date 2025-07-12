<?php

declare(strict_types=1);

namespace Freema\N8nBundle\Tests\Fixtures;

class TestResponse
{
    public function __construct(
        public readonly string $status,
        public readonly string $message,
        public readonly int $timestamp,
    ) {
    }
}
