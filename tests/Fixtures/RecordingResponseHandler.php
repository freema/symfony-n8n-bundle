<?php

declare(strict_types=1);

namespace Freema\N8nBundle\Tests\Fixtures;

use Freema\N8nBundle\Contract\N8nResponseHandlerInterface;

final class RecordingResponseHandler implements N8nResponseHandlerInterface
{
    /** @var list<array{uuid: string, data: array<mixed>}> */
    public static array $handled = [];

    public function handleN8nResponse(array $responseData, string $requestUuid): void
    {
        self::$handled[] = ['uuid' => $requestUuid, 'data' => $responseData];
    }

    public function getHandlerId(): string
    {
        return 'recording_handler';
    }
}
