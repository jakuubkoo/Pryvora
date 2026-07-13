<?php

declare(strict_types=1);

namespace App\Integration\Exception;

/**
 * The remote resource changed since we last read it (HTTP 412). Callers treat
 * this as "remote wins": drop the local push and re-pull.
 */
final class CalDavConflictException extends IntegrationException
{
}
