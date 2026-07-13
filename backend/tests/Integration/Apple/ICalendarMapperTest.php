<?php

declare(strict_types=1);

namespace App\Tests\Integration\Apple;

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

    private function ics(string $vevent): string
    {
        return "BEGIN:VCALENDAR\r\nVERSION:2.0\r\nBEGIN:VEVENT\r\n"
            .str_replace("\n", "\r\n", trim($vevent))
            ."\r\nEND:VEVENT\r\nEND:VCALENDAR";
    }
}
