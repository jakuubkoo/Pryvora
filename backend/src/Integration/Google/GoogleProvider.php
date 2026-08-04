<?php

declare(strict_types=1);

namespace App\Integration\Google;

use App\Entity\ConnectedAccount;
use App\Entity\User;
use App\Enum\IntegrationStatus;
use App\Enum\SenderVerdict;
use App\Integration\CredentialForm;
use App\Integration\Exception\IntegrationException;
use App\Integration\IntegrationProviderInterface;
use App\Integration\OAuthProviderInterface;
use App\Repository\EmailMessageRepository;
use App\Repository\SenderRuleRepository;
use App\Service\ConnectedAccountCredentials;
use App\Triage\EffectiveCategory;
use App\Triage\TriageClassifier;
use Doctrine\ORM\EntityManagerInterface;

/**
 * One Google account, two read-only services: Gmail metadata into EmailMessage,
 * and Google Calendar into CalendarEvent. They share a single ConnectedAccount
 * and a single OAuth grant, so connecting once switches both on.
 *
 * Read-only throughout. Nothing here labels, archives, sends or deletes mail,
 * and nothing writes a calendar event back to Google — iCloud remains the only
 * calendar Pryvora writes to. There is deliberately no write path in this package.
 */
final class GoogleProvider implements IntegrationProviderInterface, OAuthProviderInterface
{
    public const KEY = 'google';

    private const SCOPE = 'https://www.googleapis.com/auth/gmail.readonly';
    private const CALENDAR_SCOPE = 'https://www.googleapis.com/auth/calendar.readonly';

    private const BACKFILL_QUERY = 'in:inbox newer_than:30d';
    private const MAX_BACKFILL_MESSAGES = 500;

    /**
     * GmailClient paces itself at ~5 requests a second, so 100 messages is about
     * 20 seconds of work — a short, quiet burst rather than a long one. The
     * backfill resumes on the next scheduler tick, so a smaller number here costs
     * nothing but wall-clock on a job nobody is waiting for.
     */
    private const MAX_MESSAGES_PER_RUN = 100;
    private const PAGE_SIZE = 100;

    private const CORRESPONDENT_SAMPLE = 300;
    private const CORRESPONDENT_TTL = '-24 hours';

    /**
     * The user's sender rules, loaded once at the top of sync().
     *
     * @var array<string, SenderVerdict>
     */
    private array $sender_rules = [];

    /**
     * The calendar slice of sync state, held for the duration of one sync() so
     * that save_state — which the Gmail half calls repeatedly as it pages — can
     * write both slices without clobbering the other one.
     *
     * @var array<string, mixed>
     */
    private array $calendar_state = [];

    public function __construct(
        private readonly GoogleOAuthClient $oauth_client,
        private readonly GoogleTokenProvider $token_provider,
        private readonly GmailClient $gmail_client,
        private readonly GmailMessageMapper $mapper,
        private readonly GoogleCalendarSync $calendar_sync,
        private readonly TriageClassifier $classifier,
        private readonly EffectiveCategory $effective_category,
        private readonly ConnectedAccountCredentials $credentials,
        private readonly EmailMessageRepository $email_message_repository,
        private readonly SenderRuleRepository $sender_rule_repository,
        private readonly EntityManagerInterface $entity_manager,
    ) {
    }

    public function get_key(): string
    {
        return self::KEY;
    }

    public function get_label(): string
    {
        return 'Google (Gmail + Calendar, read-only)';
    }

    /**
     * Empty: Google is connected by redirect, and the frontend renders a button
     * rather than a form because the provider reports auth = 'oauth'.
     */
    public function get_credential_form(): CredentialForm
    {
        return new CredentialForm([]);
    }

    public function get_scopes(): array
    {
        return [self::SCOPE, self::CALENDAR_SCOPE];
    }

    public function is_configured(): bool
    {
        return $this->oauth_client->is_configured();
    }

    public function get_authorization_url(string $state): string
    {
        return $this->oauth_client->build_authorization_url($state, $this->get_scopes());
    }

    /**
     * Unreachable through the controller, which rejects the form path for OAuth
     * providers before it gets here. It exists because the interface demands it,
     * and it fails loudly rather than quietly creating a credential-less account.
     */
    public function connect(User $user, array $credentials): ConnectedAccount
    {
        throw new IntegrationException('Google is connected through Google sign-in, not a credentials form.');
    }

    public function complete_authorization(User $user, string $code, ?ConnectedAccount $existing): ConnectedAccount
    {
        $tokens = $this->oauth_client->exchange_code($code);
        $granted = array_values(array_filter(explode(' ', $tokens['scope'])));

        // The consent screen lets the user untick scopes. Finding out now beats a
        // 403 in a worker an hour later.
        if (!\in_array(self::SCOPE, $granted, true)) {
            throw new IntegrationException('Pryvora needs permission to read your Gmail. Connect again and leave the read permission ticked.');
        }

        $profile = $this->gmail_client->get_profile($tokens['access_token']);

        if ('' === $profile['emailAddress']) {
            throw new IntegrationException('Google did not return which account was connected.');
        }

        $account = $existing ?? new ConnectedAccount();

        $account->setUserOwner($user);
        $account->setProvider(self::KEY);
        $account->setExternalAccountId($profile['emailAddress']);
        $account->setDisplayName($profile['emailAddress']);
        $account->setStatus(IntegrationStatus::CONNECTED);
        $account->setLastError(null);
        $account->setScopes($granted);
        $account->setExpiresAt(new \DateTimeImmutable('@'.(time() + $tokens['expires_in'])));

        $this->credentials->write($account, [
            'access_token' => $tokens['access_token'],
            'refresh_token' => $tokens['refresh_token'],
        ]);

        if (!$account->getCreatedAt()) {
            $account->setCreatedAt(new \DateTimeImmutable());
        } else {
            $account->setUpdatedAt(new \DateTimeImmutable());
        }

        // pending_history_id, not history_id: it is only safe to trust once the
        // backfill that follows has actually finished. See sync().
        //
        // The two services keep separate slices so that a Gmail page write and a
        // calendar cursor update cannot overwrite each other.
        $account->setSyncState([
            'gmail' => [
                'history_id' => null,
                'pending_history_id' => $profile['historyId'],
                'backfill_complete' => false,
                'backfill_page_token' => null,
                'backfill_imported' => 0,
                'correspondents' => [],
                'correspondents_refreshed_at' => null,
            ],
            // Reconnecting re-reads every calendar from scratch: cheap, and it
            // repairs anything a stale cursor might have missed.
            'calendar' => [],
        ]);

        return $account;
    }

    public function test_connection(ConnectedAccount $account): bool
    {
        $this->gmail_client->get_profile($this->token_provider->get_access_token($account));

        return true;
    }

    /**
     * Both halves run on one access token and their counts are summed, so a
     * quiet mailbox with a busy calendar still reports work done.
     */
    public function sync(ConnectedAccount $account): int
    {
        $access_token = $this->token_provider->get_access_token($account);
        $state = $account->getSyncState() ?? [];

        $this->calendar_state = \is_array($state['calendar'] ?? null) ? $state['calendar'] : [];

        $synced = $this->sync_gmail($account, $access_token, \is_array($state['gmail'] ?? null) ? $state['gmail'] : []);
        $synced += $this->sync_calendar($account, $access_token);

        return $synced;
    }

    /**
     * Skipped, not failed, when the grant predates the calendar scope. An
     * account connected before Calendar existed keeps syncing mail instead of
     * erroring every 15 minutes over a permission the user never gave.
     */
    private function sync_calendar(ConnectedAccount $account, string $access_token): int
    {
        if (!\in_array(self::CALENDAR_SCOPE, $account->getScopes() ?? [], true)) {
            return 0;
        }

        [$synced, $this->calendar_state] = $this->calendar_sync->sync($account, $access_token, $this->calendar_state);

        // The Gmail half has already written its slice; re-read it so saving the
        // calendar cursor preserves whatever page it stopped on.
        $gmail_state = ($account->getSyncState() ?? [])['gmail'] ?? [];

        $this->save_state($account, \is_array($gmail_state) ? $gmail_state : []);

        return $synced;
    }

    /**
     * @param array<string, mixed> $state
     */
    private function sync_gmail(ConnectedAccount $account, string $access_token, array $state): int
    {
        $user = $account->getUserOwner();

        // Loaded once for the whole sync rather than per message: a personal block
        // list is tens of rows, and classifying 100 messages would otherwise mean
        // 100 SELECTs that all return the same thing.
        $this->sender_rules = $user instanceof User
            ? $this->sender_rule_repository->findMapForUser($user)
            : [];

        $this->refresh_correspondents($account, $access_token, $state);

        if (true !== ($state['backfill_complete'] ?? false)) {
            return $this->backfill($account, $access_token, $state);
        }

        try {
            return $this->incremental($account, $access_token, $state);
        } catch (HistoryExpiredException) {
            // Routine: Gmail forgot the cursor. Start a fresh bounded backfill
            // rather than surfacing an error the user cannot act on. This mirrors
            // how AppleCalendarProvider recovers from a dead sync token.
            $state['history_id'] = null;
            $state['backfill_complete'] = false;
            $state['backfill_page_token'] = null;
            $state['backfill_imported'] = 0;
            $state['pending_history_id'] = $this->gmail_client->get_profile($access_token)['historyId'];

            return $this->backfill($account, $access_token, $state);
        }
    }

    public function disconnect(ConnectedAccount $account): void
    {
        $bag = $this->credentials->read($account);
        $refresh_token = (string) ($bag['refresh_token'] ?? '');

        if ('' !== $refresh_token) {
            $this->oauth_client->revoke($refresh_token);
        }

        $account->setStatus(IntegrationStatus::DISCONNECTED);
    }

    /**
     * Bounded and resumable. A run stops at MAX_MESSAGES_PER_RUN and leaves a page
     * token behind; the 900s scheduler tick picks it up from there.
     *
     * @param array<string, mixed> $state
     */
    private function backfill(ConnectedAccount $account, string $access_token, array $state): int
    {
        $page_token = $state['backfill_page_token'] ?? null;

        // Cumulative across runs, not per-run: MAX_BACKFILL_MESSAGES caps how much
        // history we ever import, while MAX_MESSAGES_PER_RUN only caps how much one
        // scheduler tick chews through. Counting per-run would make the total cap
        // unreachable and the backfill would walk the whole mailbox.
        $imported = (int) ($state['backfill_imported'] ?? 0);
        $processed = 0;

        // Capture the cursor BEFORE reading any messages. Promoting it only once
        // the backfill finishes is what stops mail that arrives mid-backfill from
        // falling into a gap between "not in the backfill window" and "before the
        // history cursor" and being lost for good.
        if (null === ($state['pending_history_id'] ?? null)) {
            $state['pending_history_id'] = $this->gmail_client->get_profile($access_token)['historyId'];
        }

        do {
            $page = $this->gmail_client->list_message_ids(
                $access_token,
                self::BACKFILL_QUERY,
                \is_string($page_token) ? $page_token : null,
                self::PAGE_SIZE,
            );

            foreach ($page['ids'] as $id) {
                if ($imported >= self::MAX_BACKFILL_MESSAGES) {
                    break;
                }

                if ($this->upsert($account, $access_token, $id, $state)) {
                    ++$processed;
                    ++$imported;
                }
            }

            $this->entity_manager->flush();

            $page_token = $page['next_page_token'];
            $state['backfill_page_token'] = $page_token;
            $state['backfill_imported'] = $imported;

            $this->save_state($account, $state);
        } while (
            null !== $page_token
            && $processed < self::MAX_MESSAGES_PER_RUN
            && $imported < self::MAX_BACKFILL_MESSAGES
        );

        // Only now is the captured cursor safe to promote.
        if (null === $page_token || $imported >= self::MAX_BACKFILL_MESSAGES) {
            $state['backfill_complete'] = true;
            $state['backfill_page_token'] = null;
            $state['history_id'] = $state['pending_history_id'];

            $this->save_state($account, $state);
        }

        return $processed;
    }

    /**
     * @param array<string, mixed> $state
     */
    private function incremental(ConnectedAccount $account, string $access_token, array $state): int
    {
        $history_id = (string) ($state['history_id'] ?? '');

        if ('' === $history_id) {
            throw new HistoryExpiredException('No history cursor stored.');
        }

        $page_token = null;
        $touched = 0;
        $latest = $history_id;

        do {
            $page = $this->gmail_client->list_history($access_token, $history_id, $page_token);

            foreach ($page['history'] as $entry) {
                $touched += $this->apply_history_entry($account, $access_token, $entry, $state);
            }

            if (null !== $page['history_id']) {
                $latest = $page['history_id'];
            }

            $page_token = $page['next_page_token'];
        } while (null !== $page_token);

        $this->entity_manager->flush();

        $state['history_id'] = $latest;
        $this->save_state($account, $state);

        return $touched;
    }

    /**
     * @param array<string, mixed> $entry
     * @param array<string, mixed> $state
     */
    private function apply_history_entry(ConnectedAccount $account, string $access_token, array $entry, array $state): int
    {
        $touched = 0;

        foreach ($entry['messagesAdded'] ?? [] as $added) {
            $id = (string) ($added['message']['id'] ?? '');
            $labels = $added['message']['labelIds'] ?? [];

            // Only what actually lands in the inbox. Sent mail, drafts and things
            // Gmail already filed elsewhere are not this feature's problem.
            if ('' !== $id && \in_array('INBOX', $labels, true) && $this->upsert($account, $access_token, $id, $state)) {
                ++$touched;
            }
        }

        $deleted = [];

        foreach ($entry['messagesDeleted'] ?? [] as $removed) {
            $id = (string) ($removed['message']['id'] ?? '');

            if ('' !== $id) {
                $deleted[] = $id;
            }
        }

        if ([] !== $deleted) {
            $this->email_message_repository->deleteByAccountAndGmailIds($account, $deleted);
            $touched += \count($deleted);
        }

        // A label change can move a message between Gmail's categories, so the
        // classification has to be redone rather than just the flags updated —
        // otherwise our categories slowly rot away from Gmail's.
        $relabelled = [];

        foreach (['labelsAdded', 'labelsRemoved'] as $key) {
            foreach ($entry[$key] ?? [] as $change) {
                $id = (string) ($change['message']['id'] ?? '');

                if ('' !== $id) {
                    $relabelled[$id] = true;
                }
            }
        }

        $known = $this->email_message_repository->findByAccountAndGmailIds($account, array_keys($relabelled));

        foreach (array_keys($known) as $id) {
            if ($this->upsert($account, $access_token, $id, $state)) {
                ++$touched;
            }
        }

        return $touched;
    }

    /**
     * @param array<string, mixed> $state
     */
    private function upsert(ConnectedAccount $account, string $access_token, string $id, array $state): bool
    {
        $payload = $this->gmail_client->get_message_metadata($access_token, $id);

        /** @var list<string> $correspondents */
        $correspondents = $state['correspondents'] ?? [];

        $signals = $this->mapper->to_signals($payload, $correspondents, $account->getExternalAccountId());
        $result = $this->classifier->classify($signals);

        $existing = $this->email_message_repository->findOneByAccountAndGmailId($account, $id);
        $message = $this->mapper->to_email_message($payload, $account, $result, $existing);

        if (null === $message) {
            return false;
        }

        // The mapper set category from the classifier. Keep that as the auto verdict
        // and lay any standing rule for this sender over the top — so a re-sync
        // never resurrects a blocked sender, and un-blocking still has the original
        // verdict to fall back to.
        $auto = $message->getCategory();
        $rule = $this->sender_rules[(string) $message->getFromEmail()] ?? null;

        $message->setAutoCategory($auto);
        $message->setCategory($this->effective_category->resolve($auto, $rule));

        $this->entity_manager->persist($message);

        return true;
    }

    /**
     * Sender reputation: who the user actually writes to. Refreshed at most daily —
     * it is 300 metadata reads, and someone's correspondents do not change hourly.
     *
     * @param array<string, mixed> $state
     */
    private function refresh_correspondents(ConnectedAccount $account, string $access_token, array &$state): void
    {
        $refreshed_at = $state['correspondents_refreshed_at'] ?? null;

        if (\is_string($refreshed_at) && $refreshed_at > (new \DateTimeImmutable(self::CORRESPONDENT_TTL))->format(\DateTimeInterface::ATOM)) {
            return;
        }

        $state['correspondents'] = $this->gmail_client->list_sent_recipients($access_token, self::CORRESPONDENT_SAMPLE);
        $state['correspondents_refreshed_at'] = (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM);

        $this->save_state($account, $state);
    }

    /**
     * The Gmail half calls this repeatedly as it pages, so it always writes both
     * slices — writing only Gmail's would drop the calendar cursor and force a
     * full re-read of every calendar on the next tick.
     *
     * @param array<string, mixed> $state the Gmail slice
     */
    private function save_state(ConnectedAccount $account, array $state): void
    {
        $account->setSyncState(['gmail' => $state, 'calendar' => $this->calendar_state]);
        $account->setLastSyncedAt(new \DateTimeImmutable());

        $this->entity_manager->flush();
    }
}
