<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Enum\IntegrationStatus;
use App\Integration\CalendarWriteInterface;
use App\Integration\Exception\CalDavConflictException;
use App\Integration\ProviderRegistry;
use App\Message\DeleteRemoteCalendarEvent;
use App\Message\SyncConnectedAccount;
use App\Repository\ConnectedAccountRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsMessageHandler]
final class DeleteRemoteCalendarEventHandler
{
    public function __construct(
        private readonly ConnectedAccountRepository $connected_account_repository,
        private readonly ProviderRegistry $registry,
        private readonly EntityManagerInterface $entity_manager,
        private readonly MessageBusInterface $message_bus,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function __invoke(DeleteRemoteCalendarEvent $message): void
    {
        $account = $this->connected_account_repository->find($message->connected_account_id);

        if (!$account || IntegrationStatus::DISCONNECTED === $account->getStatus()) {
            return;
        }

        $provider = $this->registry->get((string) $account->getProvider());

        if (!$provider instanceof CalendarWriteInterface) {
            return;
        }

        try {
            $provider->delete_remote_event($account, $message->external_href, $message->external_etag);
        } catch (CalDavConflictException $exception) {
            // The event changed on iCloud after the user deleted it here.
            // Remote wins: leave it alone and re-pull, which restores it locally.
            $this->logger->warning('Calendar delete conflicted, re-pulling', [
                'account' => $account->getId(),
                'href' => $message->external_href,
                'reason' => $exception->getMessage(),
            ]);

            $this->message_bus->dispatch(new SyncConnectedAccount((int) $account->getId()));

            return;
        } catch (\Throwable $exception) {
            $account->setLastError($exception->getMessage());
            $this->entity_manager->flush();

            throw $exception;
        }

        $this->logger->info('Calendar event deleted remotely', [
            'account' => $account->getId(),
            'href' => $message->external_href,
        ]);
    }
}
