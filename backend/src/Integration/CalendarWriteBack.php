<?php

declare(strict_types=1);

namespace App\Integration;

use App\Entity\CalendarEvent;
use App\Entity\ConnectedAccount;
use App\Entity\User;
use App\Enum\IntegrationStatus;
use App\Message\DeleteRemoteCalendarEvent;
use App\Message\PushCalendarEvent;
use App\Repository\ConnectedAccountRepository;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * The seam between the calendar CRUD and the integrations. Every method is a
 * no-op unless the user actually has a write-capable account with a target
 * calendar chosen, so calendar behaviour is unchanged for everyone else.
 *
 * These are called only from EventController, i.e. only in response to a human
 * acting in Pryvora. The sync path never calls them, which is what keeps a push
 * from echoing back as another push.
 */
final class CalendarWriteBack
{
    public function __construct(
        private readonly ConnectedAccountRepository $connected_accounts,
        private readonly ProviderRegistry $registry,
        private readonly MessageBusInterface $message_bus,
    ) {
    }

    /**
     * Links a brand-new event to the user's target calendar. Must run before the
     * first flush, so the event is persisted with its UID and href already set.
     */
    public function link_new_event(CalendarEvent $event): void
    {
        $user = $event->getUserOwner();

        if (!$user instanceof User) {
            return;
        }

        foreach ($this->connected_accounts->findByUser($user) as $account) {
            if (IntegrationStatus::CONNECTED !== $account->getStatus() || null === $account->getTargetCalendarHref()) {
                continue;
            }

            $provider = $this->registry->get((string) $account->getProvider());

            if ($provider instanceof CalendarWriteInterface) {
                $provider->prepare_new_event($account, $event);

                return;
            }
        }
    }

    /**
     * Queues the remote create/replace. Call after the event is flushed, so the
     * handler can load it by id.
     */
    public function queue_upsert(CalendarEvent $event): void
    {
        if (!$this->is_linked($event)) {
            return;
        }

        $this->message_bus->dispatch(new PushCalendarEvent((int) $event->getId()));
    }

    /**
     * Captures what the remote delete will need, before the row is removed.
     * Returns null when the event only ever lived in Pryvora.
     */
    public function plan_delete(CalendarEvent $event): ?DeleteRemoteCalendarEvent
    {
        if (!$this->is_linked($event)) {
            return null;
        }

        $account = $event->getConnectedAccount();

        return new DeleteRemoteCalendarEvent(
            (int) $account?->getId(),
            (string) $event->getExternalHref(),
            $event->getExternalEtag(),
        );
    }

    /**
     * Call once the removal is committed, so a failed delete never leaves a
     * queued message that would wipe the event on iCloud anyway.
     */
    public function dispatch_delete(?DeleteRemoteCalendarEvent $message): void
    {
        if (null === $message) {
            return;
        }

        $this->message_bus->dispatch($message);
    }

    private function is_linked(CalendarEvent $event): bool
    {
        $account = $event->getConnectedAccount();

        return $account instanceof ConnectedAccount
            && IntegrationStatus::DISCONNECTED !== $account->getStatus()
            && null !== $event->getExternalHref();
    }
}
