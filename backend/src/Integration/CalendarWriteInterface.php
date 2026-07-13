<?php

declare(strict_types=1);

namespace App\Integration;

use App\Entity\CalendarEvent;
use App\Entity\ConnectedAccount;

/**
 * Implemented only by providers that can write calendar events back to the
 * remote service. Kept separate from IntegrationProviderInterface so a
 * notification-only provider (Telegram) is not forced to implement it.
 */
interface CalendarWriteInterface
{
    /**
     * Stamps a brand-new local event with its account, UID and remote href.
     * Called before the first flush, so the href is known even if the create is
     * still queued when a delete arrives.
     */
    public function prepare_new_event(ConnectedAccount $account, CalendarEvent $event): void;

    /**
     * Creates or replaces the remote resource, then refreshes the event's etag.
     */
    public function push_event(CalendarEvent $event): void;

    public function delete_remote_event(ConnectedAccount $account, string $href, ?string $etag): void;

    /**
     * Reads the calendars discovered during sync. No network call.
     *
     * @return list<array{href: string, display_name: string}>
     */
    public function list_target_calendars(ConnectedAccount $account): array;
}
