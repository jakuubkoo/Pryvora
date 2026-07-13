<?php

declare(strict_types=1);

namespace App\Message;

/**
 * Dispatched only from user-initiated actions in EventController. The pull never
 * dispatches this, which is what prevents a sync/push echo loop.
 */
final readonly class PushCalendarEvent
{
    public function __construct(public int $calendar_event_id)
    {
    }
}
