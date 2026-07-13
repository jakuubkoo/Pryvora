<?php

declare(strict_types=1);

namespace App\Integration\Apple;

use App\Entity\CalendarEvent;
use App\Entity\ConnectedAccount;
use App\Integration\Exception\IntegrationException;
use App\Service\EncryptionService;
use Sabre\VObject\Component\VCalendar;
use Sabre\VObject\Component\VEvent;
use Sabre\VObject\Property\ICalendar\DateTime as ICalDateTime;
use Sabre\VObject\Reader;

final class ICalendarMapper
{
    public function __construct(private readonly EncryptionService $encryption_service)
    {
    }

    /**
     * A client-chosen UID for an event that originated in Pryvora.
     */
    public function new_uid(): string
    {
        return bin2hex(random_bytes(16)).'@pryvora.app';
    }

    /**
     * Serializes an event back to iCalendar.
     *
     * When $existing_ics is given — an event we imported and the user then
     * edited — its master VEVENT is mutated in place, so RRULE, VALARM,
     * ATTENDEE and X- properties we do not model survive the round trip.
     * Rebuilding from scratch would silently collapse a recurring series into a
     * single occurrence.
     */
    public function to_ics(CalendarEvent $event, ?string $existing_ics = null): string
    {
        $vcalendar = null;
        $vevent = null;

        if (null !== $existing_ics) {
            $parsed = Reader::read($existing_ics, Reader::OPTION_FORGIVING);

            if ($parsed instanceof VCalendar) {
                $master = $this->find_master_event($parsed);

                if ($master instanceof VEvent) {
                    $vcalendar = $parsed;
                    $vevent = $master;
                }
            }
        }

        if (!$vcalendar instanceof VCalendar || !$vevent instanceof VEvent) {
            $vcalendar = new VCalendar();
            $vcalendar->PRODID = '-//Pryvora//Calendar//EN';
            $created = $vcalendar->add('VEVENT');

            if (!$created instanceof VEvent) {
                throw new IntegrationException('Could not build an iCalendar event.');
            }

            $vevent = $created;
        }

        $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));

        $vevent->UID = (string) $event->getExternalUid();
        $vevent->DTSTAMP = $now;
        $vevent->{'LAST-MODIFIED'} = $now;
        $vevent->SUMMARY = (string) $event->getTitle();

        $location = $event->getLocation();

        if (null !== $location && '' !== $location) {
            $vevent->LOCATION = $location;
        } else {
            unset($vevent->LOCATION);
        }

        $this->write_description($vevent, $event);
        $this->write_dates($vevent, $event);

        return $vcalendar->serialize();
    }

    /**
     * UIDs can be folded across lines, so read them through the parser rather
     * than by hand.
     */
    public function read_uid(string $ics): ?string
    {
        $vcalendar = Reader::read($ics, Reader::OPTION_FORGIVING);

        if (!$vcalendar instanceof VCalendar) {
            return null;
        }

        $vevent = $this->find_master_event($vcalendar);
        $uid = $vevent instanceof VEvent ? (string) $vevent->UID : '';

        return '' !== $uid ? $uid : null;
    }

    /**
     * Returns null when the resource holds nothing we can map (no VEVENT, or
     * only recurrence overrides, which v1 does not import).
     */
    public function to_calendar_event(string $ics, ConnectedAccount $account, ?CalendarEvent $existing): ?CalendarEvent
    {
        $vcalendar = Reader::read($ics, Reader::OPTION_FORGIVING);

        if (!$vcalendar instanceof VCalendar) {
            return null;
        }

        $vevent = $this->find_master_event($vcalendar);

        if (!$vevent instanceof VEvent) {
            return null;
        }

        $uid = (string) $vevent->UID;

        if ('' === $uid) {
            return null;
        }

        $starts_at = $this->to_utc($vevent->DTSTART);

        if (!$starts_at) {
            return null;
        }

        $all_day = $vevent->DTSTART instanceof ICalDateTime && !$vevent->DTSTART->hasTime();
        $ends_at = $this->resolve_end($vevent, $starts_at, $all_day);

        $event = $existing ?? new CalendarEvent();
        $description = $vevent->DESCRIPTION ? (string) $vevent->DESCRIPTION : null;

        $event->setUserOwner($account->getUserOwner());
        $event->setConnectedAccount($account);
        $event->setExternalUid($uid);
        $event->setTitle($this->truncate($vevent->SUMMARY ? (string) $vevent->SUMMARY : 'Untitled event', 255));
        $event->setLocation($vevent->LOCATION ? $this->truncate((string) $vevent->LOCATION, 255) : null);
        // EventController stores description as ciphertext and decrypts on read,
        // so imported descriptions must be encrypted the same way.
        $event->setDescription($description ? $this->encryption_service->encrypt($description) : '');
        $event->setStartsAt($starts_at);
        $event->setEndsAt($ends_at);
        $event->setAllDay($all_day);
        $event->setLastSyncedAt(new \DateTimeImmutable());

        if (!$event->getCreatedAt()) {
            $event->setCreatedAt(new \DateTimeImmutable());
        } else {
            $event->setUpdatedAt(new \DateTimeImmutable());
        }

        return $event;
    }

    /**
     * Recurring events are imported as their master occurrence only; RRULE is
     * not expanded yet, and RECURRENCE-ID overrides are skipped.
     */
    private function find_master_event(VCalendar $vcalendar): ?VEvent
    {
        foreach ($vcalendar->VEVENT ?? [] as $vevent) {
            if (!isset($vevent->{'RECURRENCE-ID'})) {
                return $vevent;
            }
        }

        return null;
    }

    /**
     * The description is ciphertext in the database and must go to iCloud as
     * plaintext. EventController stores '' (not null) for an empty description,
     * and decrypting '' would blow up, so guard that case.
     */
    private function write_description(VEvent $vevent, CalendarEvent $event): void
    {
        $description = $event->getDescription();

        if (null === $description || '' === $description) {
            unset($vevent->DESCRIPTION);

            return;
        }

        $vevent->DESCRIPTION = $this->encryption_service->decrypt($description);
    }

    private function write_dates(VEvent $vevent, CalendarEvent $event): void
    {
        $starts_at = $event->getStartsAt();
        $ends_at = $event->getEndsAt();

        if (!$starts_at instanceof \DateTimeImmutable || !$ends_at instanceof \DateTimeImmutable) {
            return;
        }

        $vevent->remove('DTSTART');
        $vevent->remove('DTEND');
        $vevent->remove('DURATION');

        if (true === $event->isAllDay()) {
            // DTEND is exclusive for all-day events, so a single-day event ends
            // on the following date.
            $end_date = $ends_at->setTime(0, 0);
            $start_date = $starts_at->setTime(0, 0);

            if ($end_date <= $start_date) {
                $end_date = $start_date->modify('+1 day');
            }

            $vevent->add('DTSTART', $start_date, ['VALUE' => 'DATE']);
            $vevent->add('DTEND', $end_date, ['VALUE' => 'DATE']);

            return;
        }

        // Everything goes out in UTC, so no VTIMEZONE is ever needed.
        $utc = new \DateTimeZone('UTC');

        $vevent->add('DTSTART', $starts_at->setTimezone($utc));
        $vevent->add('DTEND', $ends_at->setTimezone($utc));
    }

    private function resolve_end(VEvent $vevent, \DateTimeImmutable $starts_at, bool $all_day): \DateTimeImmutable
    {
        $ends_at = $this->to_utc($vevent->DTEND);

        if ($ends_at) {
            return $ends_at;
        }

        if (isset($vevent->DURATION)) {
            return $starts_at->add(\Sabre\VObject\DateTimeParser::parseDuration((string) $vevent->DURATION));
        }

        return $all_day ? $starts_at->modify('+1 day') : $starts_at->modify('+1 hour');
    }

    private function to_utc(mixed $property): ?\DateTimeImmutable
    {
        if (!$property instanceof ICalDateTime) {
            return null;
        }

        $date_time = $property->getDateTime();

        if (!$date_time instanceof \DateTimeInterface) {
            return null;
        }

        return \DateTimeImmutable::createFromInterface($date_time)
            ->setTimezone(new \DateTimeZone('UTC'));
    }

    private function truncate(string $value, int $length): string
    {
        return mb_strlen($value) > $length ? mb_substr($value, 0, $length) : $value;
    }
}
