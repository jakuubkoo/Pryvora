<?php

declare(strict_types=1);

namespace App\Enum;

enum IntegrationStatus: string
{
    case CONNECTED = 'connected';
    case ERROR = 'error';
    case DISCONNECTED = 'disconnected';
}
