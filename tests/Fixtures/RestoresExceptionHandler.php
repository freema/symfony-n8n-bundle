<?php

declare(strict_types=1);

namespace Freema\N8nBundle\Tests\Fixtures;

/**
 * A kernel handling a request in debug mode installs Symfony's exception
 * handler; PHPUnit flags a test that leaves it behind as risky.
 */
trait RestoresExceptionHandler
{
    /** @var callable|null */
    private $originalExceptionHandler;

    private function rememberExceptionHandler(): void
    {
        $this->originalExceptionHandler = self::currentExceptionHandler();
    }

    private function restoreExceptionHandler(): void
    {
        for ($i = 0; $i < 20 && self::currentExceptionHandler() !== $this->originalExceptionHandler; ++$i) {
            restore_exception_handler();
        }
    }

    private static function currentExceptionHandler(): ?callable
    {
        $handler = set_exception_handler(null);
        restore_exception_handler();

        return $handler;
    }
}
