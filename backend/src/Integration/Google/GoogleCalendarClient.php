<?php

declare(strict_types=1);

namespace App\Integration\Google;

use App\Integration\Exception\IntegrationException;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * The Google Calendar REST API, read paths only.
 *
 * Nothing here creates, updates or deletes a remote event. Pryvora pulls Google
 * Calendar into a unified view; iCloud remains the only calendar it writes to.
 *
 * Paced like GmailClient, for the same reason: an unpaced sync loop hammers
 * Google as fast as the network allows and is indistinguishable from a runaway
 * bug. Nobody is waiting on a background sync, so it can afford to be polite.
 */
final class GoogleCalendarClient
{
    private const BASE_URL = 'https://www.googleapis.com/calendar/v3';

    private const MIN_REQUEST_INTERVAL_MICROSECONDS = 200_000;

    private const PAGE_SIZE = 250;

    private ?float $last_request_at = null;

    public function __construct(private readonly HttpClientInterface $http_client)
    {
    }

    /**
     * The user's calendar list. Hidden and deleted collections are dropped here
     * rather than at the call site, so the sync loop only ever sees calendars
     * the user actually has switched on.
     *
     * @return list<array{id: string, display_name: string}>
     */
    public function list_calendars(string $access_token): array
    {
        $calendars = [];
        $page_token = null;

        do {
            $params = ['maxResults' => self::PAGE_SIZE, 'showHidden' => 'false'];

            if (null !== $page_token) {
                $params['pageToken'] = $page_token;
            }

            $payload = $this->get($access_token, '/users/me/calendarList', $params);

            foreach ($payload['items'] ?? [] as $item) {
                $id = (string) ($item['id'] ?? '');

                if ('' === $id || true === ($item['deleted'] ?? false) || false === ($item['selected'] ?? true)) {
                    continue;
                }

                $calendars[] = [
                    'id' => $id,
                    'display_name' => (string) ($item['summary'] ?? $id),
                ];
            }

            $page_token = isset($payload['nextPageToken']) ? (string) $payload['nextPageToken'] : null;
        } while (null !== $page_token);

        return $calendars;
    }

    /**
     * One page of events.
     *
     * There is deliberately no time window. Google only hands back a
     * nextSyncToken for an unbounded enumeration, and a window would also drop
     * a long-running recurring series whose master started years ago but which
     * still has occurrences this week. A first run therefore reads the whole
     * calendar once and every run after it is incremental.
     *
     * showDeleted is on so an incremental page can report cancellations; without
     * it a deletion on the phone would simply never reach us.
     *
     * @return array{events: list<array<string, mixed>>, next_page_token: ?string, next_sync_token: ?string}
     *
     * @throws SyncTokenExpiredException when Google has aged out $sync_token
     */
    public function list_events(string $access_token, string $calendar_id, ?string $sync_token, ?string $page_token): array
    {
        $params = [
            'maxResults' => self::PAGE_SIZE,
            // Recurring series arrive as their master with the RRULE intact,
            // matching how the Apple side imports them. Expanding would multiply
            // one series into hundreds of rows.
            'singleEvents' => 'false',
            'showDeleted' => 'true',
        ];

        if (null !== $sync_token && '' !== $sync_token) {
            $params['syncToken'] = $sync_token;
        }

        if (null !== $page_token && '' !== $page_token) {
            $params['pageToken'] = $page_token;
        }

        $payload = $this->get($access_token, '/calendars/'.rawurlencode($calendar_id).'/events', $params);

        return [
            'events' => array_values($payload['items'] ?? []),
            'next_page_token' => isset($payload['nextPageToken']) ? (string) $payload['nextPageToken'] : null,
            // Only present on the final page of a run.
            'next_sync_token' => isset($payload['nextSyncToken']) ? (string) $payload['nextSyncToken'] : null,
        ];
    }

    private function pace(): void
    {
        if (null !== $this->last_request_at) {
            $elapsed = (microtime(true) - $this->last_request_at) * 1_000_000;
            $remaining = self::MIN_REQUEST_INTERVAL_MICROSECONDS - $elapsed;

            if ($remaining > 0) {
                usleep((int) $remaining);
            }
        }

        $this->last_request_at = microtime(true);
    }

    /**
     * @param array<string, mixed> $params
     *
     * @return array<string, mixed>
     */
    private function get(string $access_token, string $path, array $params = []): array
    {
        $this->pace();

        try {
            $response = $this->http_client->request('GET', self::BASE_URL.$path, [
                'auth_bearer' => $access_token,
                'query' => $params,
            ]);

            $status = $response->getStatusCode();
            $body = $response->getContent(false);
        } catch (ExceptionInterface $exception) {
            throw new IntegrationException('Could not reach Google Calendar: '.$exception->getMessage());
        }

        if (410 === $status) {
            throw new SyncTokenExpiredException('Google Calendar no longer has changes from that point.');
        }

        if (401 === $status) {
            throw new IntegrationException('Google rejected the access token. Reconnect your Google account.');
        }

        if (429 === $status || 403 === $status) {
            throw new IntegrationException('Google Calendar is rate-limiting this account. The sync will retry.');
        }

        if ($status >= 400) {
            throw new IntegrationException('Google Calendar returned an error ('.$status.').');
        }

        $payload = json_decode($body, true);

        if (!\is_array($payload)) {
            throw new IntegrationException('Google Calendar returned an unreadable response.');
        }

        return $payload;
    }
}
