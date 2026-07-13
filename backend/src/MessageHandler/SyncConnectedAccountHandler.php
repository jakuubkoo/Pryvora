<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Enum\IntegrationStatus;
use App\Integration\ProviderRegistry;
use App\Message\SyncConnectedAccount;
use App\Repository\ConnectedAccountRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class SyncConnectedAccountHandler
{
    public function __construct(
        private readonly ConnectedAccountRepository $connected_account_repository,
        private readonly ProviderRegistry $registry,
        private readonly EntityManagerInterface $entity_manager,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function __invoke(SyncConnectedAccount $message): void
    {
        $account = $this->connected_account_repository->find($message->connected_account_id);

        if (!$account || IntegrationStatus::DISCONNECTED === $account->getStatus()) {
            return;
        }

        try {
            $synced = $this->registry->get((string) $account->getProvider())->sync($account);

            $account->setStatus(IntegrationStatus::CONNECTED);
            $account->setLastError(null);
            $account->setLastSyncedAt(new \DateTimeImmutable());

            $this->logger->info('Integration sync complete', [
                'account' => $account->getId(),
                'provider' => $account->getProvider(),
                'events' => $synced,
            ]);
        } catch (\Throwable $exception) {
            $account->setStatus(IntegrationStatus::ERROR);
            $account->setLastError($exception->getMessage());

            throw $exception;
        } finally {
            $this->entity_manager->flush();
        }
    }
}
