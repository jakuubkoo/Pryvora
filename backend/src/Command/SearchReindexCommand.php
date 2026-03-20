<?php

declare(strict_types=1);

namespace App\Command;

use App\Repository\NoteRepository;
use App\Repository\TaskRepository;
use App\Service\Search\SearchIndexer;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:search:reindex',
    description: 'Reindex all notes and tasks into Elasticsearch',
)]
class SearchReindexCommand extends Command
{
    public function __construct(
        private readonly NoteRepository $noteRepository,
        private readonly TaskRepository $taskRepository,
        private readonly SearchIndexer $searchIndexer,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $notes = $this->noteRepository->findAll();
        $io->section('Indexing notes...');
        foreach ($notes as $note) {
            $this->searchIndexer->indexNote($note);
        }
        $io->success(\sprintf('Indexed %d notes.', \count($notes)));

        $tasks = $this->taskRepository->findAll();
        $io->section('Indexing tasks...');
        foreach ($tasks as $task) {
            $this->searchIndexer->indexTask($task);
        }
        $io->success(\sprintf('Indexed %d tasks.', \count($tasks)));

        return Command::SUCCESS;
    }
}
