<?php

declare(strict_types=1);

namespace App\Integration\Google;

use App\Entity\ConnectedAccount;
use App\Entity\EmailMessage;
use App\Service\EncryptionService;
use App\Triage\TriageResult;
use App\Triage\TriageSignals;

/**
 * Turns a Gmail format=METADATA payload into the signals the classifier reads, and
 * into the row we store.
 *
 * Encrypts on write, the same way ICalendarMapper does for CalendarEvent::description:
 * the controller decrypts on read, and EncryptionService is never reached for by
 * anything else in the Gmail package.
 */
final class GmailMessageMapper
{
    /**
     * Gmail system labels, and nothing else.
     *
     * A user's own label names are their content — "Divorce lawyer", "Job hunt" —
     * and labelIds is a plaintext column. Anything not on this list is dropped
     * before it can be persisted.
     */
    private const SYSTEM_LABELS = [
        'INBOX',
        'UNREAD',
        'STARRED',
        'IMPORTANT',
        'SPAM',
        'TRASH',
        'DRAFT',
        'SENT',
        'CATEGORY_PERSONAL',
        'CATEGORY_SOCIAL',
        'CATEGORY_PROMOTIONS',
        'CATEGORY_UPDATES',
        'CATEGORY_FORUMS',
    ];

    public function __construct(private readonly EncryptionService $encryption_service)
    {
    }

    /**
     * @param array<string, mixed> $payload        a format=METADATA message
     * @param list<string>         $correspondents lowercased addresses the user has written to
     */
    public function to_signals(array $payload, array $correspondents, ?string $user_email): TriageSignals
    {
        $headers = $this->headers($payload);
        $from = $this->parse_from($headers['from'] ?? '');

        return new TriageSignals(
            from_email: $from['email'],
            from_name: $from['name'],
            subject: $headers['subject'] ?? '',
            headers: $headers,
            label_ids: $this->system_labels($payload),
            to_addresses: $this->parse_addresses($headers['to'] ?? ''),
            user_has_replied_to_sender: '' !== $from['email'] && \in_array($from['email'], $correspondents, true),
            has_in_reply_to: isset($headers['in-reply-to']) && '' !== trim($headers['in-reply-to']),
            user_email: $user_email,
        );
    }

    /**
     * @param array<string, mixed> $payload a format=METADATA message
     */
    public function to_email_message(
        array $payload,
        ConnectedAccount $account,
        TriageResult $result,
        ?EmailMessage $existing,
    ): ?EmailMessage {
        $id = (string) ($payload['id'] ?? '');

        if ('' === $id) {
            return null;
        }

        $headers = $this->headers($payload);
        $from = $this->parse_from($headers['from'] ?? '');
        $labels = $this->system_labels($payload);
        $unsubscribe = $this->parse_unsubscribe_url($headers['list-unsubscribe'] ?? '');

        $message = $existing ?? new EmailMessage();

        $message->setUserOwner($account->getUserOwner());
        $message->setConnectedAccount($account);
        $message->setGmailMessageId($id);
        $message->setGmailThreadId((string) ($payload['threadId'] ?? $id));
        $message->setSubject($this->encryption_service->encrypt($headers['subject'] ?? '(no subject)'));
        $message->setSnippet($this->encrypt_or_null($this->decode_snippet((string) ($payload['snippet'] ?? ''))));
        $message->setFromName($this->encrypt_or_null($from['name']));
        $message->setFromEmail('' !== $from['email'] ? $from['email'] : 'unknown@unknown.invalid');
        $message->setReceivedAt($this->received_at($payload));
        $message->setCategory($result->category);
        $message->setScore($result->score);
        $message->setCategoryReason($result->matched);
        $message->setIsUnread(\in_array('UNREAD', $labels, true));
        $message->setIsStarred(\in_array('STARRED', $labels, true));
        $message->setLabelIds($labels);
        $message->setHasListUnsubscribe(null !== $unsubscribe);
        $message->setUnsubscribeUrl($this->encrypt_or_null($unsubscribe));

        if (!$message->getCreatedAt()) {
            $message->setCreatedAt(new \DateTimeImmutable());
        } else {
            $message->setUpdatedAt(new \DateTimeImmutable());
        }

        return $message;
    }

    /**
     * Header names are case-insensitive per RFC 5322, and Gmail does not normalise
     * them. Lowercase once here so nothing downstream has to think about it.
     *
     * @param array<string, mixed> $payload
     *
     * @return array<string, string>
     */
    private function headers(array $payload): array
    {
        $headers = [];

        foreach ($payload['payload']['headers'] ?? [] as $header) {
            $name = strtolower(trim((string) ($header['name'] ?? '')));

            if ('' !== $name) {
                $headers[$name] = (string) ($header['value'] ?? '');
            }
        }

        return $headers;
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return list<string>
     */
    private function system_labels(array $payload): array
    {
        $labels = [];

        foreach ($payload['labelIds'] ?? [] as $label) {
            $label = (string) $label;

            if (\in_array($label, self::SYSTEM_LABELS, true)) {
                $labels[] = $label;
            }
        }

        return array_values(array_unique($labels));
    }

    /**
     * `"Jana Novak" <jana@example.com>` and bare `jana@example.com` both occur.
     *
     * @return array{name: ?string, email: string}
     */
    private function parse_from(string $value): array
    {
        $value = trim($value);

        if (preg_match('/^(.*?)\s*<([^>]+)>\s*$/', $value, $matches)) {
            $name = trim($matches[1], " \t\"'");

            return [
                'name' => '' !== $name ? $name : null,
                'email' => strtolower(trim($matches[2])),
            ];
        }

        return [
            'name' => null,
            'email' => strtolower($value),
        ];
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
     * List-Unsubscribe carries a comma-separated list of <mailto:…> and <https:…>.
     * Only the https one is useful to put in front of a human.
     */
    private function parse_unsubscribe_url(string $value): ?string
    {
        if (preg_match('/<(https?:\/\/[^>]+)>/i', $value, $matches)) {
            return trim($matches[1]);
        }

        return null;
    }

    /**
     * Gmail HTML-escapes the snippet.
     */
    private function decode_snippet(string $snippet): ?string
    {
        $decoded = trim(html_entity_decode($snippet, \ENT_QUOTES | \ENT_HTML5, 'UTF-8'));

        return '' !== $decoded ? $decoded : null;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function received_at(array $payload): \DateTimeImmutable
    {
        $internal_date = (string) ($payload['internalDate'] ?? '');

        if ('' === $internal_date || !ctype_digit($internal_date)) {
            return new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        }

        // internalDate is milliseconds since the epoch.
        return new \DateTimeImmutable('@'.intdiv((int) $internal_date, 1000));
    }

    private function encrypt_or_null(?string $value): ?string
    {
        return null !== $value && '' !== $value ? $this->encryption_service->encrypt($value) : null;
    }
}
