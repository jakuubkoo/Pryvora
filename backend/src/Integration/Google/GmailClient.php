<?php

declare(strict_types=1);

namespace App\Integration\Google;

use App\Integration\Exception\IntegrationException;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * The Gmail REST API, read paths only.
 *
 * Every fetch asks for format=METADATA, which is the read-only promise made
 * structural rather than merely intended: Gmail answers with headers, labelIds,
 * snippet, internalDate and threadId — and no body parts at all. There is no code
 * path here that could receive a message body even by mistake.
 *
 * Nothing in this class mutates anything. There is no send, no modify, no trash.
 */
final class GmailClient
{
    private const BASE_URL = 'https://gmail.googleapis.com/gmail/v1/users/me';

    /**
     * The only headers we ever ask for. Everything the classifier needs, and
     * nothing else — Gmail will happily return the full header block otherwise.
     */
    private const METADATA_HEADERS = [
        'From',
        'To',
        'Subject',
        'Date',
        'List-Id',
        'List-Unsubscribe',
        'Precedence',
        'Auto-Submitted',
        'In-Reply-To',
    ];

    /**
     * Minimum gap between two Gmail requests, in microseconds — 200ms, so ~5
     * requests a second.
     *
     * Gmail allows 250 quota units per user per second and a metadata fetch costs
     * 5, so the ceiling is ~50 requests a second. Pacing at 5 keeps us at a tenth
     * of that. This is not really about quota: an unpaced loop hammers Google as
     * fast as the network allows, which is indistinguishable from a runaway bug
     * and is a horrible thing to watch in a log. Syncing is a background job with
     * nobody waiting on it, so it can afford to be polite.
     */
    private const MIN_REQUEST_INTERVAL_MICROSECONDS = 200_000;

    private ?float $last_request_at = null;

    public function __construct(private readonly HttpClientInterface $http_client)
    {
    }

    /**
     * Sleeps just long enough that no two requests leave closer together than
     * MIN_REQUEST_INTERVAL_MICROSECONDS. Sleeping is safe here: every caller is
     * the sync worker, and no HTTP request is ever waiting on it.
     */
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
     * @return array{emailAddress: string, historyId: string, messagesTotal: int}
     */
    public function get_profile(string $access_token): array
    {
        $payload = $this->get($access_token, '/profile');

        return [
            'emailAddress' => (string) ($payload['emailAddress'] ?? ''),
            'historyId' => (string) ($payload['historyId'] ?? ''),
            'messagesTotal' => (int) ($payload['messagesTotal'] ?? 0),
        ];
    }

    /**
     * @return array{ids: list<string>, next_page_token: ?string}
     */
    public function list_message_ids(string $access_token, string $query, ?string $page_token, int $max_results): array
    {
        $params = [
            'q' => $query,
            'maxResults' => $max_results,
        ];

        if (null !== $page_token && '' !== $page_token) {
            $params['pageToken'] = $page_token;
        }

        $payload = $this->get($access_token, '/messages', $params);
        $ids = [];

        foreach ($payload['messages'] ?? [] as $message) {
            if (isset($message['id'])) {
                $ids[] = (string) $message['id'];
            }
        }

        return [
            'ids' => $ids,
            'next_page_token' => isset($payload['nextPageToken']) ? (string) $payload['nextPageToken'] : null,
        ];
    }

    /**
     * @return array<string, mixed> the raw format=METADATA payload
     */
    public function get_message_metadata(string $access_token, string $id): array
    {
        return $this->get($access_token, '/messages/'.rawurlencode($id), [
            'format' => 'metadata',
            'metadataHeaders' => self::METADATA_HEADERS,
        ]);
    }

    /**
     * @return array{history: list<array<string, mixed>>, next_page_token: ?string, history_id: ?string}
     *
     * @throws HistoryExpiredException when Gmail has forgotten $start_history_id
     */
    public function list_history(string $access_token, string $start_history_id, ?string $page_token): array
    {
        $params = [
            'startHistoryId' => $start_history_id,
            'historyTypes' => ['messageAdded', 'messageDeleted', 'labelAdded', 'labelRemoved'],
        ];

        if (null !== $page_token && '' !== $page_token) {
            $params['pageToken'] = $page_token;
        }

        $payload = $this->get($access_token, '/history', $params);

        return [
            'history' => array_values($payload['history'] ?? []),
            'next_page_token' => isset($payload['nextPageToken']) ? (string) $payload['nextPageToken'] : null,
            'history_id' => isset($payload['historyId']) ? (string) $payload['historyId'] : null,
        ];
    }

    /**
     * The addresses the user has written TO, which is the raw material for sender
     * reputation. Metadata-only, and only the To header.
     *
     * @return list<string> lowercased, deduplicated
     */
    public function list_sent_recipients(string $access_token, int $limit): array
    {
        $page = $this->list_message_ids($access_token, 'in:sent newer_than:1y', null, min($limit, 500));
        $addresses = [];

        foreach ($page['ids'] as $id) {
            $payload = $this->get($access_token, '/messages/'.rawurlencode($id), [
                'format' => 'metadata',
                'metadataHeaders' => ['To'],
            ]);

            foreach ($payload['payload']['headers'] ?? [] as $header) {
                if ('to' !== strtolower((string) ($header['name'] ?? ''))) {
                    continue;
                }

                foreach ($this->parse_addresses((string) ($header['value'] ?? '')) as $address) {
                    $addresses[$address] = true;
                }
            }
        }

        return array_keys($addresses);
    }

    /**
     * @return list<string>
     */
    private function parse_addresses(string $value): array
    {
        preg_match_all('/[\w.+-]+@[\w-]+\.[\w.-]+/', $value, $matches);

        return array_values(array_unique(array_map('strtolower', $matches[0])));
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
            throw new IntegrationException('Could not reach Gmail: '.$exception->getMessage());
        }

        if (404 === $status && str_starts_with($path, '/history')) {
            throw new HistoryExpiredException('Gmail no longer has history from that point.');
        }

        if (401 === $status) {
            throw new IntegrationException('Google rejected the access token. Reconnect Gmail.');
        }

        // Quota. The messenger retry_strategy backs off and tries again, which is
        // the whole reason sync runs in a worker rather than in the request.
        if (429 === $status || 403 === $status) {
            throw new IntegrationException('Gmail is rate-limiting this account. The sync will retry.');
        }

        if ($status >= 400) {
            throw new IntegrationException('Gmail returned an error ('.$status.').');
        }

        $payload = json_decode($body, true);

        if (!\is_array($payload)) {
            throw new IntegrationException('Gmail returned an unreadable response.');
        }

        return $payload;
    }
}
