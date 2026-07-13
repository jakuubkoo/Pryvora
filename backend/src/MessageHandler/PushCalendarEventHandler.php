<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Entity\ConnectedAccount;
use App\Enum\IntegrationStatus;
use App\Integration\CalendarWriteInterface;
use App\Integration\Exception\CalDavConflictException;
use App\Integration\ProviderRegistry;
use App\Message\PushCalendarEvent;
use App\Message\SyncConnectedAccount;
use App\Repository\CalendarEventRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsMessageHandler]
final class PushCalendarEventHandler
{
    public function __construct(
        private readonly CalendarEventRepository $calendar_event_repository,
        private readonly ProviderRegistry $registry,
        private readonly EntityManagerInterface $entity_manager,
        private readonly MessageBusInterface $message_bus,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function __invoke(PushCalendarEvent $message): void
    {
        $event = $this->calendar_event_repository->find($message->calendar_event_id);

        // Deleted before the push ran. The delete carries its own message.
        if (!$event) {
            return;
        }

        $account = $event->getConnectedAccount();

        if (!$account instanceof ConnectedAccount || IntegrationStatus::DISCONNECTED === $account->getStatus()) {
            return;
        }

        $provider = $this->registry->get((string) $account->getProvider());

        if (!$provider instanceof CalendarWriteInterface) {
            return;
        }

        try {
            $provider->push_event($event);
        } catch (CalDavConflictException $exception) {
            // Remote wins: abandon the push and re-pull, so the local row ends
            // up matching what is actually on the calendar.
            $this->logger->warning('Calendar push conflicted, re-pulling', [
                'event' => $event->getId(),
                'account' => $account->getId(),
                'reason' => $exception->getMessage(),
            ]);

            $this->message_bus->dispatch(new SyncConnectedAccount((int) $account->getId()));

            return;
        } catch (\Throwable $exception) {
            $account->setLastError($exception->getMessage());
            $this->entity_manager->flush();

            throw $exception;
        }

        $this->logger->info('Calendar event pushed', [
            'event' => $event->getId(),
            'account' => $account->getId(),
        ]);
    }
}
