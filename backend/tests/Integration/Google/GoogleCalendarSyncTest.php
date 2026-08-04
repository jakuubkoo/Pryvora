<?php

declare(strict_types=1);

namespace App\Tests\Integration\Google;

use App\Entity\ConnectedAccount;
use App\Entity\User;
use App\Integration\Google\GoogleCalendarClient;
use App\Integration\Google\GoogleCalendarMapper;
use App\Integration\Google\GoogleCalendarSync;
use App\Repository\CalendarEventRepository;
use App\Service\EncryptionService;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

/**
 * Drives the sync loop against a mocked Google, through the real client — the
 * paging and 410 handling live in the client, so stubbing it out would test the
 * half that cannot break.
 */
class GoogleCalendarSyncTest extends KernelTestCase
{
    private const TOKEN = 'access-token';

    /** @var list<string> */
    private array $requested = [];

    protected function setUp(): void
    {
        parent::setUp();
        self::bootKernel();

        $this->requested = [];
    }

    public function test_first_run_enumerates_and_stores_a_cursor(): void
    {
        $account = $this->account();

        $sync = $this->sync([
            $this->calendar_list(),
            $this->events([$this->event('a', 'Dentist')], next_sync_token: 'cursor-1'),
        ]);

        [$count, $state] = $sync->sync($account, self::TOKEN, []);

        $this->assertSame(1, $count);
        $this->assertSame('cursor-1', $state['calendars'][0]['sync_token']);
        $this->assertSame('work', $state['calendars'][0]['id']);

        $event = $this->repository()->findOneByAccountAndExternalUid($account, 'work/a');

        $this->assertNotNull($event);
        $this->assertSame('Dentist', $event->getTitle());
    }

    /**
     * The cursor is only sent once it exists, so a first run must not carry a
     * syncToken and a second run must.
     */
    public function test_second_run_is_incremental(): void
    {
        $account = $this->account();

        $sync = $this->sync([
            $this->calendar_list(),
            $this->events([$this->event('b', 'Pneu výměna')], next_sync_token: 'cursor-2'),
        ]);

        [$count, $state] = $sync->sync($account, self::TOKEN, [
            'calendars' => [['id' => 'work', 'display_name' => 'Work', 'sync_token' => 'cursor-1']],
        ]);

        $this->assertSame(1, $count);
        $this->assertSame('cursor-2', $state['calendars'][0]['sync_token']);
        $this->assertStringContainsString('syncToken=cursor-1', $this->requested[1]);
    }

    public function test_pages_until_the_cursor_arrives(): void
    {
        $account = $this->account();

        $sync = $this->sync([
            $this->calendar_list(),
            $this->events([$this->event('a', 'One')], next_page_token: 'page-2'),
            $this->events([$this->event('b', 'Two')], next_sync_token: 'cursor-1'),
        ]);

        [$count, $state] = $sync->sync($account, self::TOKEN, []);

        $this->assertSame(2, $count);
        $this->assertSame('cursor-1', $state['calendars'][0]['sync_token']);
        $this->assertStringContainsString('pageToken=page-2', $this->requested[2]);
    }

    /**
     * A dead cursor is routine, not a failure: Google answers 410 and the
     * documented recovery is a full re-read, exactly as the Apple provider
     * recovers from a rejected CalDAV token.
     */
    public function test_expired_cursor_falls_back_to_a_full_read(): void
    {
        $account = $this->account();

        $sync = $this->sync([
            $this->calendar_list(),
            new MockResponse('{"error":{"code":410}}', ['http_code' => 410]),
            $this->events([$this->event('a', 'Recovered')], next_sync_token: 'cursor-fresh'),
        ]);

        [$count, $state] = $sync->sync($account, self::TOKEN, [
            'calendars' => [['id' => 'work', 'display_name' => 'Work', 'sync_token' => 'stale']],
        ]);

        $this->assertSame(1, $count);
        $this->assertSame('cursor-fresh', $state['calendars'][0]['sync_token']);
        // The retry drops the dead token instead of replaying it.
        $this->assertStringNotContainsString('syncToken', $this->requested[2]);
    }

    public function test_cancelled_event_is_removed(): void
    {
        $account = $this->account();

        $this->sync([
            $this->calendar_list(),
            $this->events([$this->event('a', 'Dentist')], next_sync_token: 'cursor-1'),
        ])->sync($account, self::TOKEN, []);

        $this->assertNotNull($this->repository()->findOneByAccountAndExternalUid($account, 'work/a'));

        [$count] = $this->sync([
            $this->calendar_list(),
            $this->events([['id' => 'a', 'status' => 'cancelled']], next_sync_token: 'cursor-2'),
        ])->sync($account, self::TOKEN, [
            'calendars' => [['id' => 'work', 'display_name' => 'Work', 'sync_token' => 'cursor-1']],
        ]);

        $this->assertSame(1, $count);
        $this->assertNull($this->repository()->findOneByAccountAndExternalUid($account, 'work/a'));
    }

    /**
     * Google rejects the sync token for its public holiday calendars on every
     * run, so a full re-read is permanent rather than exceptional. Unchanged
     * events must not be rewritten, or each tick churns hundreds of rows.
     */
    public function test_unchanged_events_are_not_rewritten(): void
    {
        $account = $this->account();
        $event = ['etag' => '"tag-1"'] + $this->event('a', 'Dentist');

        $this->sync([
            $this->calendar_list(),
            $this->events([$event], next_sync_token: 'cursor-1'),
        ])->sync($account, self::TOKEN, []);

        [$count] = $this->sync([
            $this->calendar_list(),
            $this->events([$event], next_sync_token: 'cursor-2'),
        ])->sync($account, self::TOKEN, []);

        $this->assertSame(0, $count);
    }

    public function test_changed_etag_still_updates(): void
    {
        $account = $this->account();

        $this->sync([
            $this->calendar_list(),
            $this->events([['etag' => '"tag-1"'] + $this->event('a', 'Dentist')], next_sync_token: 'cursor-1'),
        ])->sync($account, self::TOKEN, []);

        [$count] = $this->sync([
            $this->calendar_list(),
            $this->events([['etag' => '"tag-2"'] + $this->event('a', 'Dentist, moved')], next_sync_token: 'cursor-2'),
        ])->sync($account, self::TOKEN, []);

        $this->assertSame(1, $count);
        $this->assertSame(
            'Dentist, moved',
            $this->repository()->findOneByAccountAndExternalUid($account, 'work/a')->getTitle(),
        );
    }

    /**
     * A run that ends without a fresh cursor must keep the old one, or every
     * sync would silently re-read the whole calendar.
     */
    public function test_missing_cursor_keeps_the_previous_one(): void
    {
        [, $state] = $this->sync([
            $this->calendar_list(),
            $this->events([]),
        ])->sync($this->account(), self::TOKEN, [
            'calendars' => [['id' => 'work', 'display_name' => 'Work', 'sync_token' => 'cursor-1']],
        ]);

        $this->assertSame('cursor-1', $state['calendars'][0]['sync_token']);
    }

    public function test_hidden_and_deleted_calendars_are_skipped(): void
    {
        $sync = $this->sync([
            new MockResponse(json_encode(['items' => [
                ['id' => 'work', 'summary' => 'Work'],
                ['id' => 'old', 'summary' => 'Old', 'deleted' => true],
                ['id' => 'muted', 'summary' => 'Muted', 'selected' => false],
            ]])),
            $this->events([], next_sync_token: 'cursor-1'),
        ]);

        [, $state] = $sync->sync($this->account(), self::TOKEN, []);

        $this->assertCount(1, $state['calendars']);
        $this->assertSame('work', $state['calendars'][0]['id']);
    }

    /**
     * @param list<MockResponse> $responses
     */
    private function sync(array $responses): GoogleCalendarSync
    {
        $http_client = new MockHttpClient(function (string $method, string $url) use (&$responses): MockResponse {
            $this->requested[] = $url;

            return array_shift($responses) ?? new MockResponse('{}');
        });

        $container = self::getContainer();

        return new GoogleCalendarSync(
            new GoogleCalendarClient($http_client),
            new GoogleCalendarMapper($container->get(EncryptionService::class)),
            $this->repository(),
            $container->get('doctrine')->getManager(),
        );
    }

    private function repository(): CalendarEventRepository
    {
        return self::getContainer()->get(CalendarEventRepository::class);
    }

    private function account(): ConnectedAccount
    {
        $entity_manager = self::getContainer()->get('doctrine')->getManager();

        $user = new User();
        $user->setFirstName('Test');
        $user->setLastName('User');
        $user->setEmail(uniqid('sync', true).'@example.com');
        $user->setPassword('hashed_password');

        $account = new ConnectedAccount();
        $account->setUserOwner($user);
        $account->setProvider('google');
        $account->setCredentials('');
        $account->setCreatedAt(new \DateTimeImmutable());

        $entity_manager->persist($user);
        $entity_manager->persist($account);
        $entity_manager->flush();

        return $account;
    }

    private function calendar_list(): MockResponse
    {
        return new MockResponse(json_encode(['items' => [['id' => 'work', 'summary' => 'Work']]]));
    }

    /**
     * @param list<array<string, mixed>> $events
     */
    private function events(array $events, ?string $next_page_token = null, ?string $next_sync_token = null): MockResponse
    {
        $payload = ['items' => $events];

        if (null !== $next_page_token) {
            $payload['nextPageToken'] = $next_page_token;
        }

        if (null !== $next_sync_token) {
            $payload['nextSyncToken'] = $next_sync_token;
        }

        return new MockResponse(json_encode($payload));
    }

    /**
     * @return array<string, mixed>
     */
    private function event(string $id, string $summary): array
    {
        return [
            'id' => $id,
            'summary' => $summary,
            'status' => 'confirmed',
            'start' => ['dateTime' => '2026-07-13T09:00:00Z'],
            'end' => ['dateTime' => '2026-07-13T10:00:00Z'],
        ];
    }
}
