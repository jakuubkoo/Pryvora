<?php

declare(strict_types=1);

namespace App\Integration\Google;

use App\Integration\Exception\IntegrationException;

/**
 * Google answers 410 Gone when a stored syncToken has aged out. Routine, not a
 * failure: the documented recovery is a fresh bounded enumeration, exactly like
 * HistoryExpiredException for Gmail and a rejected sync token for CalDAV.
 */
final class SyncTokenExpiredException extends IntegrationException
{
}
