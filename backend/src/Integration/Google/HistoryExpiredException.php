<?php

declare(strict_types=1);

namespace App\Integration\Google;

use App\Integration\Exception\IntegrationException;

/**
 * Gmail keeps history for roughly a week and then forgets it, answering 404 to a
 * startHistoryId it no longer knows. That is a routine "fall back to a backfill"
 * signal, not a failure — a distinct type so the provider branches on it rather
 * than on the text of an error message.
 */
final class HistoryExpiredException extends IntegrationException
{
}
