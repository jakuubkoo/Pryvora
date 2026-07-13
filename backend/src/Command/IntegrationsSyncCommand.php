<?php

declare(strict_types=1);

namespace App\Command;

use App\Message\SyncConnectedAccount;
use App\Repository\ConnectedAccountRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsCommand(
    name: 'app:integrations:sync',
    description: 'Queue a background sync for every connected integration account',
)]
class IntegrationsSyncCommand extends Command
{
    public function __construct(
        private readonly ConnectedAccountRepository $connectedAccountRepository,
        private readonly MessageBusInterface $messageBus,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('account', null, InputOption::VALUE_REQUIRED, 'Sync only this connected account id');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $account_id = $input->getOption('account');

        if (null !== $account_id) {
            $account = $this->connectedAccountRepository->find((int) $account_id);

            if (!$account) {
                $io->error(\sprintf('No connected account with id %d.', (int) $account_id));

                return Command::FAILURE;
            }

            $accounts = [$account];
        } else {
            $accounts = $this->connectedAccountRepository->findConnected();
        }

        foreach ($accounts as $account) {
            $this->messageBus->dispatch(new SyncConnectedAccount((int) $account->getId()));
        }

        $io->success(\sprintf('Queued %d account(s).', \count($accounts)));

        return Command::SUCCESS;
    }
}
