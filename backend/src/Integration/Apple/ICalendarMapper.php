<?php

declare(strict_types=1);

namespace App\Integration\Apple;

use App\Entity\CalendarEvent;
use App\Entity\ConnectedAccount;
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
