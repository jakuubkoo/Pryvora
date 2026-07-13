<?php

declare(strict_types=1);

namespace App\Tests\Integration\Apple;

use App\Entity\CalendarEvent;
use App\Entity\ConnectedAccount;
use App\Entity\User;
use App\Integration\Apple\ICalendarMapper;
use App\Service\EncryptionService;
use PHPUnit\Framework\TestCase;

class ICalendarMapperTest extends TestCase
{
    private ICalendarMapper $mapper;
    private EncryptionService $encryption_service;
    private ConnectedAccount $account;

    protected function setUp(): void
    {
        $this->encryption_service = new EncryptionService(base64_encode(random_bytes(32)));
        $this->mapper = new ICalendarMapper($this->encryption_service);

        $this->account = new ConnectedAccount();
        $this->account->setUserOwner(new User());
        $this->account->setProvider('apple_calendar');
    }

    public function testMapsTimedEvent(): void
    {
        $event = $this->mapper->to_calendar_event($this->ics(<<<'ICS'
            UID:timed-1
            SUMMARY:Standup
            LOCATION:Room 2
            DTSTART:20260713T090000Z
            DTEND:20260713T093000Z
            ICS), $this->account, null);

        $this->assertNotNull($event);
        $this->assertSame('timed-1', $event->getExternalUid());
        $this->assertSame('Standup', $event->getTitle());
        $this->assertSame('Room 2', $event->getLocation());
        $this->assertFalse($event->isAllDay());
        $this->assertSame('2026-07-13 09:00:00', $event->getStartsAt()->format('Y-m-d H:i:s'));
        $this->assertSame('2026-07-13 09:30:00', $event->getEndsAt()->format('Y-m-d H:i:s'));
        $this->assertSame($this->account, $event->getConnectedAccount());
    }

    public function testMapsAllDayEvent(): void
    {
        $event = $this->mapper->to_calendar_event($this->ics(<<<'ICS'
            UID:allday-1
            SUMMARY:Holiday
            DTSTART;VALUE=DATE:20260714
            DTEND;VALUE=DATE:20260715
            ICS), $this->account, null);

        $this->assertNotNull($event);
        $this->assertTrue($event->isAllDay());
        $this->assertSame('2026-07-14', $event->getStartsAt()->format('Y-m-d'));
    }

    public function testConvertsLocalTimeToUtc(): void
    {
        $event = $this->mapper->to_calendar_event($this->ics(<<<'ICS'
            UID:tz-1
            SUMMARY:Lunch
            DTSTART;TZID=Europe/Prague:20260713T120000
            DTEND;TZID=Europe/Prague:20260713T130000
            ICS), $this->account, null);

        $this->assertNotNull($event);
        $this->assertSame('2026-07-13 10:00:00', $event->getStartsAt()->format('Y-m-d H:i:s'));
    }

    public function testMissingEndFallsBackToOneHour(): void
    {
        $event = $this->mapper->to_calendar_event($this->ics(<<<'ICS'
            UID:noend-1
            SUMMARY:Open ended
            DTSTART:20260713T090000Z
            ICS), $this->account, null);

        $this->assertNotNull($event);
        $this->assertSame('2026-07-13 10:00:00', $event->getEndsAt()->format('Y-m-d H:i:s'));
    }

    public function testDurationIsUsedWhenEndIsAbsent(): void
    {
        $event = $this->mapper->to_calendar_event($this->ics(<<<'ICS'
            UID:duration-1
            SUMMARY:Workshop
            DTSTART:20260713T090000Z
            DURATION:PT2H30M
            ICS), $this->account, null);

        $this->assertNotNull($event);
        $this->assertSame('2026-07-13 11:30:00', $event->getEndsAt()->format('Y-m-d H:i:s'));
    }

    public function testDescriptionIsEncrypted(): void
    {
        $event = $this->mapper->to_calendar_event($this->ics(<<<'ICS'
            UID:secret-1
            SUMMARY:Private
            DESCRIPTION:Bring the passport
            DTSTART:20260713T090000Z
            DTEND:20260713T100000Z
            ICS), $this->account, null);

        $this->assertNotNull($event);
        $this->assertStringNotContainsString('passport', $event->getDescription());
        $this->assertSame('Bring the passport', $this->encryption_service->decrypt($event->getDescription()));
    }

    public function testRecurrenceOverrideIsSkipped(): void
    {
        $ics = <<<'ICS'
            BEGIN:VCALENDAR
            VERSION:2.0
            BEGIN:VEVENT
            UID:series-1
            RECURRENCE-ID:20260713T090000Z
            SUMMARY:Moved occurrence
            DTSTART:20260713T110000Z
            DTEND:20260713T120000Z
            END:VEVENT
            END:VCALENDAR
            ICS;

        $this->assertNull($this->mapper->to_calendar_event($ics, $this->account, null));
    }

    public function testRecurringMasterIsImportedUnexpanded(): void
    {
        $event = $this->mapper->to_calendar_event($this->ics(<<<'ICS'
            UID:weekly-1
            SUMMARY:Weekly sync
            DTSTART:20260713T090000Z
            DTEND:20260713T093000Z
            RRULE:FREQ=WEEKLY;COUNT=10
            ICS), $this->account, null);

        $this->assertNotNull($event);
        $this->assertSame('2026-07-13 09:00:00', $event->getStartsAt()->format('Y-m-d H:i:s'));
    }

    public function testReadUidHandlesFoldedLines(): void
    {
        $ics = "BEGIN:VCALENDAR\r\nVERSION:2.0\r\nBEGIN:VEVENT\r\nUID:very-long-uid-that-is\r\n -folded-across-lines\r\nSUMMARY:Folded\r\nDTSTART:20260713T090000Z\r\nEND:VEVENT\r\nEND:VCALENDAR";

        $this->assertSame('very-long-uid-that-is-folded-across-lines', $this->mapper->read_uid($ics));
    }

    public function testExistingEventIsUpdatedInPlace(): void
    {
        $first = $this->mapper->to_calendar_event($this->ics(<<<'ICS'
            UID:update-1
            SUMMARY:Original
            DTSTART:20260713T090000Z
            DTEND:20260713T100000Z
            ICS), $this->account, null);

        $second = $this->mapper->to_calendar_event($this->ics(<<<'ICS'
            UID:update-1
            SUMMARY:Renamed
            DTSTART:20260713T090000Z
            DTEND:20260713T100000Z
            ICS), $this->account, $first);

        $this->assertSame($first, $second);
        $this->assertSame('Renamed', $second->getTitle());
        $this->assertNotNull($second->getUpdatedAt());
    }

    public function testToIcsSerializesTimedEventInUtc(): void
    {
        $event = $this->local_event('Standup', new \DateTimeImmutable('2026-07-13 09:00:00', new \DateTimeZone('UTC')), new \DateTimeImmutable('2026-07-13 09:30:00', new \DateTimeZone('UTC')), false);
        $event->setExternalUid('push-1');
        $event->setLocation('Room 2');

        $ics = $this->mapper->to_ics($event);

        $this->assertStringContainsString('UID:push-1', $ics);
        $this->assertStringContainsString('SUMMARY:Standup', $ics);
        $this->assertStringContainsString('LOCATION:Room 2', $ics);
        $this->assertStringContainsString('DTSTART:20260713T090000Z', $ics);
        $this->assertStringContainsString('DTEND:20260713T093000Z', $ics);
    }

    public function testToIcsSerializesAllDayEventAsADate(): void
    {
        $event = $this->local_event('Holiday', new \DateTimeImmutable('2026-07-14 00:00:00', new \DateTimeZone('UTC')), new \DateTimeImmutable('2026-07-15 00:00:00', new \DateTimeZone('UTC')), true);
        $event->setExternalUid('allday-push');

        $ics = $this->mapper->to_ics($event);

        $this->assertStringContainsString('DTSTART;VALUE=DATE:20260714', $ics);
        $this->assertStringContainsString('DTEND;VALUE=DATE:20260715', $ics);
        $this->assertStringNotContainsString('DTSTART:', $ics);
    }

    public function testToIcsGivesAllDayEventAnExclusiveEnd(): void
    {
        // DTEND is exclusive for all-day events, so a same-day event must end
        // on the following date or Apple renders nothing.
        $day = new \DateTimeImmutable('2026-07-14 00:00:00', new \DateTimeZone('UTC'));
        $event = $this->local_event('One day', $day, $day, true);
        $event->setExternalUid('allday-same');

        $ics = $this->mapper->to_ics($event);

        $this->assertStringContainsString('DTSTART;VALUE=DATE:20260714', $ics);
        $this->assertStringContainsString('DTEND;VALUE=DATE:20260715', $ics);
    }

    public function testToIcsSendsThePlaintextDescriptionNotTheCiphertext(): void
    {
        $event = $this->local_event('Private', new \DateTimeImmutable('2026-07-13 09:00:00', new \DateTimeZone('UTC')), new \DateTimeImmutable('2026-07-13 10:00:00', new \DateTimeZone('UTC')), false);
        $event->setExternalUid('secret-push');
        $event->setDescription($this->encryption_service->encrypt('Bring the passport'));

        $ics = $this->mapper->to_ics($event);

        $this->assertStringContainsString('Bring the passport', $ics);
        $this->assertStringNotContainsString($event->getDescription(), $ics);
    }

    public function testToIcsHandlesTheEmptyDescriptionEventControllerWrites(): void
    {
        // EventController stores '' (not null) when there is no description, and
        // decrypting '' would blow up.
        $event = $this->local_event('No notes', new \DateTimeImmutable('2026-07-13 09:00:00', new \DateTimeZone('UTC')), new \DateTimeImmutable('2026-07-13 10:00:00', new \DateTimeZone('UTC')), false);
        $event->setExternalUid('empty-desc');
        $event->setDescription('');

        $ics = $this->mapper->to_ics($event);

        $this->assertStringNotContainsString('DESCRIPTION', $ics);
    }

    public function testToIcsMergePreservesRecurrenceAndAlarms(): void
    {
        $existing = $this->ics(<<<'ICS'
            UID:weekly-1
            SUMMARY:Old title
            DTSTART:20260713T090000Z
            DTEND:20260713T093000Z
            RRULE:FREQ=WEEKLY;COUNT=10
            BEGIN:VALARM
            ACTION:DISPLAY
            TRIGGER:-PT15M
            END:VALARM
            ICS);

        $event = $this->local_event('New title', new \DateTimeImmutable('2026-07-13 09:00:00', new \DateTimeZone('UTC')), new \DateTimeImmutable('2026-07-13 09:30:00', new \DateTimeZone('UTC')), false);
        $event->setExternalUid('weekly-1');

        $ics = $this->mapper->to_ics($event, $existing);

        $this->assertStringContainsString('SUMMARY:New title', $ics);
        $this->assertStringNotContainsString('Old title', $ics);
        // Rebuilding from scratch would silently collapse the series.
        $this->assertStringContainsString('RRULE:FREQ=WEEKLY;COUNT=10', $ics);
        $this->assertStringContainsString('BEGIN:VALARM', $ics);
        $this->assertStringContainsString('TRIGGER:-PT15M', $ics);
    }

    public function testNewUidIsUnique(): void
    {
        $this->assertNotSame($this->mapper->new_uid(), $this->mapper->new_uid());
        $this->assertStringEndsWith('@pryvora.app', $this->mapper->new_uid());
    }

    public function testRoundTripSurvivesToIcsAndBack(): void
    {
        $event = $this->local_event('Round trip', new \DateTimeImmutable('2026-07-13 09:00:00', new \DateTimeZone('UTC')), new \DateTimeImmutable('2026-07-13 10:30:00', new \DateTimeZone('UTC')), false);
        $event->setExternalUid('round-1');
        $event->setDescription($this->encryption_service->encrypt('notes here'));

        $reparsed = $this->mapper->to_calendar_event($this->mapper->to_ics($event), $this->account, null);

        $this->assertNotNull($reparsed);
        $this->assertSame('Round trip', $reparsed->getTitle());
        $this->assertSame('2026-07-13 09:00:00', $reparsed->getStartsAt()->format('Y-m-d H:i:s'));
        $this->assertSame('2026-07-13 10:30:00', $reparsed->getEndsAt()->format('Y-m-d H:i:s'));
        $this->assertSame('notes here', $this->encryption_service->decrypt($reparsed->getDescription()));
    }

    private function local_event(string $title, \DateTimeImmutable $starts_at, \DateTimeImmutable $ends_at, bool $all_day): CalendarEvent
    {
        $event = new CalendarEvent();
        $event->setUserOwner($this->account->getUserOwner());
        $event->setConnectedAccount($this->account);
        $event->setTitle($title);
        $event->setStartsAt($starts_at);
        $event->setEndsAt($ends_at);
        $event->setAllDay($all_day);
        $event->setDescription('');
        $event->setCreatedAt(new \DateTimeImmutable());

        return $event;
    }

    private function ics(string $vevent): string
    {
        return "BEGIN:VCALENDAR\r\nVERSION:2.0\r\nBEGIN:VEVENT\r\n"
            .str_replace("\n", "\r\n", trim($vevent))
            ."\r\nEND:VEVENT\r\nEND:VCALENDAR";
    }
}
