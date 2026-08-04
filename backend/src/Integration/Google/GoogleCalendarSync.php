<?php

declare(strict_types=1);

namespace App\Integration\Google;

use App\Entity\ConnectedAccount;
use App\Repository\CalendarEventRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * The calendar half of GoogleProvider::sync().
 *
 * Structurally the same loop as AppleCalendarProvider: re-discover calendars
 * every run, enumerate in full when there is no cursor, otherwise ask only for
 * what changed, and fall back to a full enumeration when the cursor dies.
 *
 * It is a collaborator rather than a provider of its own because Gmail and
 * Calendar share one Google account and one OAuth grant, so there is only ever
 * one ConnectedAccount row to sync.
 */
final class GoogleCalendarSync
{
    public function __construct(
        private readonly GoogleCalendarClient $client,
        private readonly GoogleCalendarMapper $mapper,
        private readonly CalendarEventRepository $calendar_event_repository,
        private readonly EntityManagerInterface $entity_manager,
    ) {
    }

    /**
     * @param array<string, mixed> $state the calendar slice of the account's sync state
     *
     * @return array{0: int, 1: array{calendars: list<array{id: string, display_name: string, sync_token: ?string}>}}
     */
    public function sync(ConnectedAccount $account, string $access_token, array $state): array
    {
        $known = [];

        foreach ($state['calendars'] ?? [] as $calendar) {
            $known[(string) $calendar['id']] = $calendar['sync_token'] ?? null;
        }

        $calendars = [];
        $synced = 0;

        // Re-discovered every run, so a calendar added on the phone starts
        // syncing on the next tick instead of at the next reconnect.
        foreach ($this->client->list_calendars($access_token) as $calendar) {
            $id = $calendar['id'];
            $sync_token = $known[$id] ?? null;

            try {
                [$count, $next_token] = $this->pull($account, $access_token, $id, $sync_token);
            } catch (SyncTokenExpiredException) {
                [$count, $next_token] = $this->pull($account, $access_token, $id, null);
            }

            $synced += $count;

            $calendars[] = [
                'id' => $id,
                'display_name' => $calendar['display_name'],
                // Keeping the old token when a run ends without a new one means
                // the next sync retries incrementally rather than silently
                // falling back to a full re-read of the calendar.
                'sync_token' => $next_token ?? $sync_token,
            ];
        }

        return [$synced, ['calendars' => $calendars]];
    }

    /**
     * @return array{0: int, 1: ?string} events touched, and the cursor for next time
     *
     * @throws SyncTokenExpiredException
     */
    private function pull(ConnectedAccount $account, string $access_token, string $calendar_id, ?string $sync_token): array
    {
        $page_token = null;
        $next_sync_token = null;
        $count = 0;

        do {
            $page = $this->client->list_events($access_token, $calendar_id, $sync_token, $page_token);

            foreach ($page['events'] as $event) {
                if ($this->apply($account, $calendar_id, $event)) {
                    ++$count;
                }
            }

            $this->entity_manager->flush();

            // Google sends this on the last page only.
            $next_sync_token = $page['next_sync_token'] ?? $next_sync_token;
            $page_token = $page['next_page_token'];
        } while (null !== $page_token);

        return [$count, $next_sync_token];
    }

    /**
     * Never dispatches a push. That is what keeps the pull path one-way: an
     * event read from Google stops here rather than echoing back out.
     *
     * @param array<string, mixed> $event
     */
    private function apply(ConnectedAccount $account, string $calendar_id, array $event): bool
    {
        $uid = $this->mapper->external_uid($calendar_id, $event);

        if (null === $uid) {
            return false;
        }

        $existing = $this->calendar_event_repository->findOneByAccountAndExternalUid($account, $uid);

        if ($this->mapper->is_cancelled($event)) {
            if (null === $existing) {
                return false;
            }

            $this->entity_manager->remove($existing);

            return true;
        }

        // A rejected sync token means re-reading the whole calendar, and Google
        // rejects one on every run for its public holiday calendars — so most of
        // what arrives is unchanged. The etag is the server's own change marker:
        // an identical one means there is genuinely nothing to write, which
        // keeps a permanent 410 loop from rewriting hundreds of rows a tick.
        $etag = (string) ($event['etag'] ?? '');

        if (null !== $existing && '' !== $etag && $existing->getExternalEtag() === $etag) {
            return false;
        }

        $mapped = $this->mapper->to_calendar_event($event, $calendar_id, $account, $existing);

        if (null === $mapped) {
            return false;
        }

        $this->entity_manager->persist($mapped);

        return true;
    }
}
