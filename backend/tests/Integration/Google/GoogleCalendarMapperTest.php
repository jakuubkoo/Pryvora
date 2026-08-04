<?php

declare(strict_types=1);

namespace App\Tests\Integration\Google;

use App\Entity\CalendarEvent;
use App\Entity\ConnectedAccount;
use App\Entity\User;
use App\Integration\Google\GoogleCalendarMapper;
use App\Service\EncryptionService;
use PHPUnit\Framework\TestCase;

class GoogleCalendarMapperTest extends TestCase
{
    private const CALENDAR_ID = 'primary@example.com';

    private GoogleCalendarMapper $mapper;
    private EncryptionService $encryption_service;
    private ConnectedAccount $account;

    protected function setUp(): void
    {
        $this->encryption_service = new EncryptionService(base64_encode(random_bytes(32)));
        $this->mapper = new GoogleCalendarMapper($this->encryption_service);

        $this->account = new ConnectedAccount();
        $this->account->setUserOwner(new User());
        $this->account->setProvider('google');
    }

    public function testMapsTimedEvent(): void
    {
        $event = $this->map([
            'id' => 'abc123',
            'summary' => 'Standup',
            'location' => 'Room 2',
            'etag' => '"tag-1"',
            'htmlLink' => 'https://calendar.google.com/event?eid=abc123',
            'start' => ['dateTime' => '2026-07-13T09:00:00Z'],
            'end' => ['dateTime' => '2026-07-13T09:30:00Z'],
        ]);

        $this->assertNotNull($event);
        $this->assertSame('Standup', $event->getTitle());
        $this->assertSame('Room 2', $event->getLocation());
        $this->assertFalse($event->isAllDay());
        $this->assertSame('2026-07-13 09:00:00', $event->getStartsAt()->format('Y-m-d H:i:s'));
        $this->assertSame('2026-07-13 09:30:00', $event->getEndsAt()->format('Y-m-d H:i:s'));
        $this->assertSame('"tag-1"', $event->getExternalEtag());
        $this->assertSame('https://calendar.google.com/event?eid=abc123', $event->getExternalHref());
    }

    /**
     * The uniqueness constraint is (connected_account_id, external_uid), but a
     * Google event id is only unique inside one calendar. Two calendars under
     * one account would collide on a bare id.
     */
    public function testUidIsScopedToItsCalendar(): void
    {
        $payload = ['id' => 'shared-id'];

        $this->assertSame('work/shared-id', $this->mapper->external_uid('work', $payload));
        $this->assertNotSame(
            $this->mapper->external_uid('work', $payload),
            $this->mapper->external_uid('home', $payload),
        );
    }

    public function testUidIsNullWithoutAnId(): void
    {
        $this->assertNull($this->mapper->external_uid('work', []));
    }

    /**
     * Google's all-day end date is exclusive, and so is the value the Apple
     * mapper stores. Keeping them the same is what lets one calendar view render
     * both without knowing where a row came from.
     */
    public function testAllDayEndStaysExclusive(): void
    {
        $event = $this->map([
            'id' => 'holiday',
            'summary' => 'Holiday',
            'start' => ['date' => '2026-08-04'],
            'end' => ['date' => '2026-08-05'],
        ]);

        $this->assertNotNull($event);
        $this->assertTrue($event->isAllDay());
        $this->assertSame('2026-08-04 00:00:00', $event->getStartsAt()->format('Y-m-d H:i:s'));
        $this->assertSame('2026-08-05 00:00:00', $event->getEndsAt()->format('Y-m-d H:i:s'));
    }

    public function testConvertsZonedTimeToUtc(): void
    {
        $event = $this->map([
            'id' => 'zoned',
            'start' => ['dateTime' => '2026-07-13T14:00:00+02:00', 'timeZone' => 'Europe/Prague'],
            'end' => ['dateTime' => '2026-07-13T15:00:00+02:00', 'timeZone' => 'Europe/Prague'],
        ]);

        $this->assertNotNull($event);
        $this->assertSame('2026-07-13 12:00:00', $event->getStartsAt()->format('Y-m-d H:i:s'));
        $this->assertSame('2026-07-13 13:00:00', $event->getEndsAt()->format('Y-m-d H:i:s'));
    }

    public function testFallsBackWhenEndIsMissing(): void
    {
        $timed = $this->map([
            'id' => 'no-end',
            'start' => ['dateTime' => '2026-07-13T09:00:00Z'],
        ]);

        $all_day = $this->map([
            'id' => 'no-end-all-day',
            'start' => ['date' => '2026-07-13'],
        ]);

        $this->assertSame('2026-07-13 10:00:00', $timed->getEndsAt()->format('Y-m-d H:i:s'));
        $this->assertSame('2026-07-14 00:00:00', $all_day->getEndsAt()->format('Y-m-d H:i:s'));
    }

    public function testDescriptionIsStoredEncrypted(): void
    {
        $event = $this->map([
            'id' => 'secret',
            'description' => 'Bring the signed contract',
            'start' => ['dateTime' => '2026-07-13T09:00:00Z'],
            'end' => ['dateTime' => '2026-07-13T10:00:00Z'],
        ]);

        $this->assertNotNull($event);
        $this->assertNotSame('Bring the signed contract', $event->getDescription());
        $this->assertSame(
            'Bring the signed contract',
            $this->encryption_service->decrypt($event->getDescription()),
        );
    }

    /**
     * Decrypting '' throws, and EventController stores '' for "no description",
     * so an empty one must stay empty rather than become ciphertext.
     */
    public function testEmptyDescriptionStaysEmpty(): void
    {
        $event = $this->map([
            'id' => 'plain',
            'start' => ['dateTime' => '2026-07-13T09:00:00Z'],
            'end' => ['dateTime' => '2026-07-13T10:00:00Z'],
        ]);

        $this->assertSame('', $event->getDescription());
    }

    /**
     * Only the master of a series is imported. Importing overrides too would
     * show one appointment as several rows.
     */
    public function testRecurrenceInstanceIsSkipped(): void
    {
        $this->assertNull($this->map([
            'id' => 'series_20260713',
            'recurringEventId' => 'series',
            'start' => ['dateTime' => '2026-07-13T09:00:00Z'],
            'end' => ['dateTime' => '2026-07-13T10:00:00Z'],
        ]));
    }

    public function testRecurringMasterIsImportedUnexpanded(): void
    {
        $event = $this->map([
            'id' => 'series',
            'summary' => 'Weekly review',
            'recurrence' => ['RRULE:FREQ=WEEKLY;COUNT=10'],
            'start' => ['dateTime' => '2026-07-13T09:00:00Z'],
            'end' => ['dateTime' => '2026-07-13T10:00:00Z'],
        ]);

        $this->assertNotNull($event);
        $this->assertSame('Weekly review', $event->getTitle());
    }

    public function testCancelledEventIsRecognised(): void
    {
        $this->assertTrue($this->mapper->is_cancelled(['id' => 'gone', 'status' => 'cancelled']));
        $this->assertFalse($this->mapper->is_cancelled(['id' => 'here', 'status' => 'confirmed']));
        $this->assertFalse($this->mapper->is_cancelled(['id' => 'here']));
    }

    public function testEventWithoutStartIsSkipped(): void
    {
        $this->assertNull($this->map(['id' => 'startless', 'summary' => 'Nowhere']));
    }

    public function testUntitledEventGetsAPlaceholder(): void
    {
        $event = $this->map([
            'id' => 'blank',
            'summary' => '   ',
            'start' => ['dateTime' => '2026-07-13T09:00:00Z'],
        ]);

        $this->assertSame('Untitled event', $event->getTitle());
    }

    public function testLongTitleIsTruncatedToColumnWidth(): void
    {
        $event = $this->map([
            'id' => 'long',
            'summary' => str_repeat('a', 400),
            'start' => ['dateTime' => '2026-07-13T09:00:00Z'],
        ]);

        $this->assertSame(255, mb_strlen($event->getTitle()));
    }

    /**
     * An update has to land on the row already holding the event, otherwise the
     * unique index turns every re-sync into a constraint violation.
     */
    public function testExistingEventIsUpdatedInPlace(): void
    {
        $existing = new CalendarEvent();
        $existing->setCreatedAt(new \DateTimeImmutable('2026-01-01'));

        $event = $this->map([
            'id' => 'abc123',
            'summary' => 'Renamed',
            'start' => ['dateTime' => '2026-07-13T09:00:00Z'],
            'end' => ['dateTime' => '2026-07-13T10:00:00Z'],
        ], $existing);

        $this->assertSame($existing, $event);
        $this->assertSame('Renamed', $event->getTitle());
        $this->assertNotNull($event->getUpdatedAt());
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function map(array $payload, ?CalendarEvent $existing = null): ?CalendarEvent
    {
        return $this->mapper->to_calendar_event($payload, self::CALENDAR_ID, $this->account, $existing);
    }
}
