<?php

declare(strict_types=1);

namespace Freema\N8nBundle\Enum;

enum CommunicationMode: string
{
    case FIRE_AND_FORGET = 'fire_and_forget';
    case ASYNC_WITH_CALLBACK = 'async_with_callback';
    case SYNC = 'sync';
}
