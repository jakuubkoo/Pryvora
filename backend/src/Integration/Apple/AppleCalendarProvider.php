<?php

declare(strict_types=1);

namespace App\Integration\Apple;

use App\Entity\CalendarEvent;
use App\Entity\ConnectedAccount;
use App\Entity\User;
use App\Enum\IntegrationStatus;
use App\Integration\CalendarWriteInterface;
use App\Integration\CredentialForm;
use App\Integration\Exception\IntegrationException;
use App\Integration\IntegrationProviderInterface;
use App\Repository\CalendarEventRepository;
use App\Service\ConnectedAccountCredentials;
use Doctrine\ORM\EntityManagerInterface;

final class AppleCalendarProvider implements IntegrationProviderInterface, CalendarWriteInterface
{
    public const KEY = 'apple_calendar';

    private const BACKFILL_PAST = '-90 days';
    private const BACKFILL_FUTURE = '+365 days';

    public function __construct(
        private readonly CalDavClient $caldav_client,
        private readonly ICalendarMapper $mapper,
        private readonly ConnectedAccountCredentials $credentials,
        private readonly CalendarEventRepository $calendar_event_repository,
        private readonly EntityManagerInterface $entity_manager,
    ) {
    }

    public function get_key(): string
    {
        return self::KEY;
    }

    public function get_label(): string
    {
        return 'Apple Calendar (iCloud)';
    }

    public function get_credential_form(): CredentialForm
    {
        return new CredentialForm([
            [
                'name' => 'apple_id',
                'label' => 'Apple ID email',
                'type' => 'text',
                'required' => true,
            ],
            [
                'name' => 'app_password',
                'label' => 'App-specific password',
                'type' => 'password',
                'required' => true,
                'help' => 'Generate one at appleid.apple.com under Sign-In and Security. iCloud rejects your normal Apple ID password.',
            ],
        ]);
    }

    public function connect(User $user, array $credentials): ConnectedAccount
    {
        $apple_id = trim($credentials['apple_id'] ?? '');
        $app_password = trim($credentials['app_password'] ?? '');

        if ('' === $apple_id || '' === $app_password) {
            throw new IntegrationException('Apple ID and app-specific password are both required.');
        }

        // Running discovery here is what makes a wrong password fail at the
        // form instead of silently in a worker later.
        $principal_url = $this->caldav_client->discover_principal($apple_id, $app_password);
        $home_url = $this->caldav_client->discover_calendar_home($principal_url, $apple_id, $app_password);
        $calendars = $this->caldav_client->list_calendars($home_url, $apple_id, $app_password);

        if (!$calendars) {
            throw new IntegrationException('No event calendars were found in this iCloud account.');
        }

        $account = new ConnectedAccount();
        $account->setUserOwner($user);
        $account->setProvider(self::KEY);
        $account->setExternalAccountId($principal_url);
        $account->setDisplayName($apple_id);
        $account->setStatus(IntegrationStatus::CONNECTED);
        $account->setCreatedAt(new \DateTimeImmutable());
        $account->setSyncState([
            'calendar_home' => $home_url,
            'calendars' => array_map(
                static fn (array $calendar): array => [
                    'href' => $calendar['href'],
                    'display_name' => $calendar['display_name'],
                    'sync_token' => null,
                ],
                $calendars
            ),
        ]);

        // With a single calendar there is nothing to guess. With several,
        // leave it unset rather than silently writing to whichever came back
        // first, which could be a shared or work calendar.
        if (1 === \count($calendars)) {
            $account->setTargetCalendarHref($calendars[0]['href']);
        }

        $this->credentials->write($account, [
            'apple_id' => $apple_id,
            'app_password' => $app_password,
        ]);

        return $account;
    }

    public function test_connection(ConnectedAccount $account): bool
    {
        [$apple_id, $app_password] = $this->read_credentials($account);

        $this->caldav_client->discover_principal($apple_id, $app_password);

        return true;
    }

    public function sync(ConnectedAccount $account): int
    {
        [$apple_id, $app_password] = $this->read_credentials($account);

        $sync_state = $account->getSyncState() ?? [];
        $calendars = $this->refresh_calendars($sync_state, $apple_id, $app_password);
        $synced = 0;

        foreach ($calendars as $index => $calendar) {
            $href = $calendar['href'];
            $sync_token = $calendar['sync_token'] ?? null;

            if (null === $sync_token) {
                $synced += $this->backfill($account, $href, $apple_id, $app_password);
                $calendars[$index]['sync_token'] = $this->capture_sync_token($href, $apple_id, $app_password);

                continue;
            }

            try {
                $changes = $this->caldav_client->sync_collection($href, $sync_token, $apple_id, $app_password);
            } catch (IntegrationException) {
                // A rejected token means the server expired it; a bounded
                // backfill is the documented recovery.
                $synced += $this->backfill($account, $href, $apple_id, $app_password);
                $calendars[$index]['sync_token'] = $this->capture_sync_token($href, $apple_id, $app_password);

                continue;
            }

            $synced += $this->apply_changes($account, $href, $changes, $apple_id, $app_password);
            $calendars[$index]['sync_token'] = $changes['sync_token'] ?? $sync_token;
        }

        $sync_state['calendars'] = $calendars;
        $account->setSyncState($sync_state);
        $account->setUpdatedAt(new \DateTimeImmutable());

        $this->entity_manager->flush();

        return $synced;
    }

    public function disconnect(ConnectedAccount $account): void
    {
        $account->setStatus(IntegrationStatus::DISCONNECTED);
    }

    public function prepare_new_event(ConnectedAccount $account, CalendarEvent $event): void
    {
        $target = $account->getTargetCalendarHref();

        if (null === $target) {
            return;
        }

        $uid = $this->mapper->new_uid();

        // The href is chosen up front, so a delete queued right after a create
        // still knows what to remove even if the PUT has not run yet.
        $slug = substr($uid, 0, strpos($uid, '@') ?: \strlen($uid));

        $event->setConnectedAccount($account);
        $event->setExternalUid($uid);
        $event->setExternalHref(rtrim($target, '/').'/'.$slug.'.ics');
        $event->setExternalEtag(null);
    }

    public function push_event(CalendarEvent $event): void
    {
        $account = $event->getConnectedAccount();
        $href = $event->getExternalHref();

        if (!$account instanceof ConnectedAccount || null === $href) {
            return;
        }

        [$apple_id, $app_password] = $this->read_credentials($account);

        $etag = $event->getExternalEtag();
        $existing_ics = null;

        if (null !== $etag) {
            // Re-read the remote copy so the edit merges into whatever is
            // actually there (RRULE, alarms) and If-Match uses a current tag.
            $calendar_href = $this->calendar_href_for($account, $href);
            $remote = $this->caldav_client->multiget($calendar_href, [$href], $apple_id, $app_password);

            if (isset($remote[$href])) {
                $existing_ics = $remote[$href]['ics'];
                $etag = $remote[$href]['etag'];
            }
        }

        $ics = $this->mapper->to_ics($event, $existing_ics);
        $new_etag = $this->caldav_client->put_event($href, $ics, $etag, $apple_id, $app_password);

        $event->setExternalEtag($new_etag);
        $event->setLastSyncedAt(new \DateTimeImmutable());

        $this->entity_manager->flush();
    }

    public function delete_remote_event(ConnectedAccount $account, string $href, ?string $etag): void
    {
        [$apple_id, $app_password] = $this->read_credentials($account);

        $this->caldav_client->delete_event($href, $etag, $apple_id, $app_password);
    }

    public function list_target_calendars(ConnectedAccount $account): array
    {
        $calendars = $account->getSyncState()['calendars'] ?? [];
        $result = [];

        foreach ($calendars as $calendar) {
            $result[] = [
                'href' => (string) $calendar['href'],
                'display_name' => (string) ($calendar['display_name'] ?? 'Calendar'),
            ];
        }

        return $result;
    }

    /**
     * Which collection a resource lives in. Derived from the href rather than
     * stored, since a resource is always a direct child of its calendar.
     */
    private function calendar_href_for(ConnectedAccount $account, string $event_href): string
    {
        foreach ($this->list_target_calendars($account) as $calendar) {
            if (str_starts_with($event_href, rtrim($calendar['href'], '/').'/')) {
                return $calendar['href'];
            }
        }

        return substr($event_href, 0, (int) strrpos($event_href, '/') + 1);
    }

    /**
     * Re-discovers calendars on every sync, so a calendar added on the phone
     * shows up in Settings. Existing sync tokens are preserved; a newly seen
     * calendar starts at null and gets backfilled.
     *
     * @param array<string, mixed> $sync_state
     *
     * @return list<array{href: string, display_name: string, sync_token: ?string}>
     */
    private function refresh_calendars(array $sync_state, string $apple_id, string $app_password): array
    {
        $known = [];

        foreach ($sync_state['calendars'] ?? [] as $calendar) {
            $known[$calendar['href']] = $calendar['sync_token'] ?? null;
        }

        $home = $sync_state['calendar_home'] ?? null;

        if (!\is_string($home)) {
            $fallback = [];

            foreach ($known as $href => $sync_token) {
                $fallback[] = [
                    'href' => (string) $href,
                    'display_name' => 'Calendar',
                    'sync_token' => $sync_token,
                ];
            }

            return $fallback;
        }

        $discovered = $this->caldav_client->list_calendars($home, $apple_id, $app_password);
        $calendars = [];

        foreach ($discovered as $calendar) {
            $calendars[] = [
                'href' => $calendar['href'],
                'display_name' => $calendar['display_name'],
                'sync_token' => $known[$calendar['href']] ?? null,
            ];
        }

        return $calendars;
    }

    /**
     * @param array{sync_token: ?string, changed: list<string>, deleted: list<string>} $changes
     */
    private function apply_changes(ConnectedAccount $account, string $calendar_href, array $changes, string $apple_id, string $app_password): int
    {
        foreach ($changes['deleted'] as $deleted_href) {
            $event = $this->calendar_event_repository->findOneByAccountAndExternalHref($account, $deleted_href);

            if ($event) {
                $this->entity_manager->remove($event);
            }
        }

        $bodies = $this->caldav_client->multiget($calendar_href, $changes['changed'], $apple_id, $app_password);

        return $this->upsert($account, $bodies);
    }

    private function backfill(ConnectedAccount $account, string $calendar_href, string $apple_id, string $app_password): int
    {
        $now = new \DateTimeImmutable();

        $bodies = $this->caldav_client->calendar_query(
            $calendar_href,
            $now->modify(self::BACKFILL_PAST),
            $now->modify(self::BACKFILL_FUTURE),
            $apple_id,
            $app_password
        );

        return $this->upsert($account, $bodies);
    }

    /**
     * A bounded calendar-query returns no sync token, so grab one separately.
     * This response carries etags only, not event bodies.
     */
    private function capture_sync_token(string $calendar_href, string $apple_id, string $app_password): ?string
    {
        try {
            return $this->caldav_client->sync_collection($calendar_href, null, $apple_id, $app_password)['sync_token'];
        } catch (IntegrationException) {
            return null;
        }
    }

    /**
     * The pull never dispatches a push. That is what stops a write-back echo:
     * a pushed event comes back on the next sync, lands here, and stops.
     *
     * @param array<string, array{etag: ?string, ics: string}> $resources href => resource
     */
    private function upsert(ConnectedAccount $account, array $resources): int
    {
        $count = 0;

        foreach ($resources as $href => $resource) {
            $uid = $this->mapper->read_uid($resource['ics']);

            if (null === $uid) {
                continue;
            }

            $existing = $this->calendar_event_repository->findOneByAccountAndExternalUid($account, $uid);
            $event = $this->mapper->to_calendar_event($resource['ics'], $account, $existing);

            if (null === $event) {
                continue;
            }

            $event->setExternalHref($href);
            $event->setExternalEtag($resource['etag']);
            $this->entity_manager->persist($event);
            ++$count;
        }

        $this->entity_manager->flush();

        return $count;
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function read_credentials(ConnectedAccount $account): array
    {
        $bag = $this->credentials->read($account);

        if (!isset($bag['apple_id'], $bag['app_password'])) {
            throw new IntegrationException('Stored Apple credentials are incomplete. Reconnect the account.');
        }

        return [$bag['apple_id'], $bag['app_password']];
    }
}
