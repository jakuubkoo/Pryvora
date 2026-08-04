<?php

declare(strict_types=1);

namespace App\Integration\Google;

use App\Entity\CalendarEvent;
use App\Entity\ConnectedAccount;
use App\Service\EncryptionService;

/**
 * Google Calendar's event JSON to CalendarEvent.
 *
 * Deliberately mirrors ICalendarMapper's decisions — UTC everywhere, exclusive
 * all-day ends, encrypted descriptions, recurring series imported unexpanded —
 * so an Apple event and a Google event land in the database looking the same and
 * the calendar view does not have to care where a row came from.
 *
 * There is no to_google() counterpart: the Google integration is read-only.
 */
final class GoogleCalendarMapper
{
    public function __construct(private readonly EncryptionService $encryption_service)
    {
    }

    /**
     * Google event ids are unique within a calendar, not across an account, and
     * the uniqueness constraint is (connected_account_id, external_uid). One
     * account holds several calendars, so the calendar has to be part of the key.
     *
     * @param array<string, mixed> $event
     */
    public function external_uid(string $calendar_id, array $event): ?string
    {
        $id = (string) ($event['id'] ?? '');

        return '' !== $id ? $calendar_id.'/'.$id : null;
    }

    /**
     * A deleted event still comes back on an incremental page, as a tombstone
     * carrying little more than an id and this status.
     *
     * @param array<string, mixed> $event
     */
    public function is_cancelled(array $event): bool
    {
        return 'cancelled' === ($event['status'] ?? '');
    }

    /**
     * Returns null when the payload holds nothing we import: a recurrence
     * instance, or an event with no id or no start.
     *
     * @param array<string, mixed> $event
     */
    public function to_calendar_event(array $event, string $calendar_id, ConnectedAccount $account, ?CalendarEvent $existing): ?CalendarEvent
    {
        $uid = $this->external_uid($calendar_id, $event);

        if (null === $uid) {
            return null;
        }

        // A single modified occurrence of a series. The master carries the RRULE
        // and is imported on its own; importing overrides too would produce
        // duplicate-looking rows for one appointment.
        if (isset($event['recurringEventId'])) {
            return null;
        }

        $all_day = isset($event['start']['date']);
        $starts_at = $this->to_utc($event['start'] ?? []);

        if (!$starts_at instanceof \DateTimeImmutable) {
            return null;
        }

        $ends_at = $this->to_utc($event['end'] ?? [])
            ?? $starts_at->modify($all_day ? '+1 day' : '+1 hour');

        $calendar_event = $existing ?? new CalendarEvent();
        $description = (string) ($event['description'] ?? '');
        $location = (string) ($event['location'] ?? '');
        $title = trim((string) ($event['summary'] ?? ''));

        $calendar_event->setUserOwner($account->getUserOwner());
        $calendar_event->setConnectedAccount($account);
        $calendar_event->setExternalUid($uid);
        $calendar_event->setTitle($this->truncate('' !== $title ? $title : 'Untitled event', 255));
        $calendar_event->setLocation('' !== $location ? $this->truncate($location, 255) : null);
        // EventController stores description as ciphertext and decrypts on read,
        // so imported descriptions must be encrypted the same way. '' stays ''
        // rather than becoming ciphertext for an empty string.
        $calendar_event->setDescription('' !== $description ? $this->encryption_service->encrypt($description) : '');
        $calendar_event->setStartsAt($starts_at);
        $calendar_event->setEndsAt($ends_at);
        $calendar_event->setAllDay($all_day);
        $calendar_event->setExternalEtag($this->truncate((string) ($event['etag'] ?? ''), 255) ?: null);
        $calendar_event->setExternalHref($this->truncate((string) ($event['htmlLink'] ?? ''), 512) ?: null);
        $calendar_event->setLastSyncedAt(new \DateTimeImmutable());

        if (!$calendar_event->getCreatedAt()) {
            $calendar_event->setCreatedAt(new \DateTimeImmutable());
        } else {
            $calendar_event->setUpdatedAt(new \DateTimeImmutable());
        }

        return $calendar_event;
    }

    /**
     * Google sends either {"date": "2026-08-04"} for all-day or {"dateTime":
     * "...", "timeZone": "..."} for timed. An all-day date is anchored at
     * midnight UTC, which keeps it on the day the user picked regardless of
     * where they are reading it; a timed event carries its own offset and is
     * simply converted.
     *
     * @param array<string, mixed> $point
     */
    private function to_utc(array $point): ?\DateTimeImmutable
    {
        $utc = new \DateTimeZone('UTC');
        $date = (string) ($point['date'] ?? '');

        if ('' !== $date) {
            $parsed = \DateTimeImmutable::createFromFormat('!Y-m-d', $date, $utc);

            return false !== $parsed ? $parsed : null;
        }

        $date_time = (string) ($point['dateTime'] ?? '');

        if ('' === $date_time) {
            return null;
        }

        try {
            return (new \DateTimeImmutable($date_time))->setTimezone($utc);
        } catch (\Exception) {
            return null;
        }
    }

    private function truncate(string $value, int $length): string
    {
        return mb_strlen($value) > $length ? mb_substr($value, 0, $length) : $value;
    }
}
