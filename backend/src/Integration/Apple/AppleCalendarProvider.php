<?php

declare(strict_types=1);

namespace App\Integration\Apple;

use App\Entity\ConnectedAccount;
use App\Entity\User;
use App\Enum\IntegrationStatus;
use App\Integration\CredentialForm;
use App\Integration\Exception\IntegrationException;
use App\Integration\IntegrationProviderInterface;
use App\Repository\CalendarEventRepository;
use App\Service\ConnectedAccountCredentials;
use Doctrine\ORM\EntityManagerInterface;

final class AppleCalendarProvider implements IntegrationProviderInterface
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
        $calendars = $sync_state['calendars'] ?? [];
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
     * @param array<string, string> $bodies href => iCalendar
     */
    private function upsert(ConnectedAccount $account, array $bodies): int
    {
        $count = 0;

        foreach ($bodies as $href => $ics) {
            $uid = $this->mapper->read_uid($ics);

            if (null === $uid) {
                continue;
            }

            $existing = $this->calendar_event_repository->findOneByAccountAndExternalUid($account, $uid);
            $event = $this->mapper->to_calendar_event($ics, $account, $existing);

            if (null === $event) {
                continue;
            }

            $event->setExternalHref($href);
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
