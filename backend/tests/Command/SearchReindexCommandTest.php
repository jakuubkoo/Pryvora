<?php

declare(strict_types=1);

namespace App\Tests\Command;

use App\Command\SearchReindexCommand;
use App\Entity\Note;
use App\Entity\Task;
use App\Repository\NoteRepository;
use App\Repository\TaskRepository;
use App\Service\Search\SearchIndexer;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

#[AllowMockObjectsWithoutExpectations]
class SearchReindexCommandTest extends TestCase
{
    /** @var MockObject&NoteRepository */
    private MockObject $noteRepositoryMock;
    /** @var MockObject&TaskRepository */
    private MockObject $taskRepositoryMock;
    /** @var MockObject&SearchIndexer */
    private MockObject $searchIndexerMock;
    private CommandTester $commandTester;

    protected function setUp(): void
    {
        parent::setUp();

        $this->noteRepositoryMock = $this->createMock(NoteRepository::class);
        $this->taskRepositoryMock = $this->createMock(TaskRepository::class);
        $this->searchIndexerMock = $this->createMock(SearchIndexer::class);

        $command = new SearchReindexCommand(
            $this->noteRepositoryMock,
            $this->taskRepositoryMock,
            $this->searchIndexerMock
        );

        $this->commandTester = new CommandTester($command);
    }

    protected function tearDown(): void
    {
        unset($this->noteRepositoryMock);
        unset($this->taskRepositoryMock);
        unset($this->searchIndexerMock);
        unset($this->commandTester);

        parent::tearDown();
    }

    public function test_execute_with_no_entities(): void
    {
        $this->noteRepositoryMock->expects($this->once())
            ->method('findAll')
            ->willReturn([]);

        $this->taskRepositoryMock->expects($this->once())
            ->method('findAll')
            ->willReturn([]);

        $this->searchIndexerMock->expects($this->once())
            ->method('createIndex');

        $this->commandTester->execute([]);

        $this->commandTester->assertCommandIsSuccessful();

        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('Creating index...', $output);
        $this->assertStringContainsString('Index ready.', $output);
        $this->assertStringContainsString('Indexed 0 notes.', $output);
        $this->assertStringContainsString('Indexed 0 tasks.', $output);
    }

    public function test_execute_with_notes_and_tasks(): void
    {
        $note1 = $this->createMockNote(1, 'Note 1');
        $note2 = $this->createMockNote(2, 'Note 2');
        $task1 = $this->createMockTask(1, 'Task 1');
        $task2 = $this->createMockTask(2, 'Task 2');
        $task3 = $this->createMockTask(3, 'Task 3');

        $this->noteRepositoryMock->expects($this->once())
            ->method('findAll')
            ->willReturn([$note1, $note2]);

        $this->taskRepositoryMock->expects($this->once())
            ->method('findAll')
            ->willReturn([$task1, $task2, $task3]);

        $this->searchIndexerMock->expects($this->once())
            ->method('createIndex');

        $this->searchIndexerMock->expects($this->exactly(2))
            ->method('indexNote')
            ->willReturnCallback(function ($arg) use ($note1, $note2) {
                static $calls = 0;
                if ($calls === 0) {
                    $this->assertSame($note1, $arg);
                } elseif ($calls === 1) {
                    $this->assertSame($note2, $arg);
                }
                $calls++;
            });

        $this->searchIndexerMock->expects($this->exactly(3))
            ->method('indexTask')
            ->willReturnCallback(function ($arg) use ($task1, $task2, $task3) {
                static $calls = 0;
                if ($calls === 0) {
                    $this->assertSame($task1, $arg);
                } elseif ($calls === 1) {
                    $this->assertSame($task2, $arg);
                } elseif ($calls === 2) {
                    $this->assertSame($task3, $arg);
                }
                $calls++;
            });

        $this->commandTester->execute([]);

        $this->commandTester->assertCommandIsSuccessful();

        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('Indexing notes...', $output);
        $this->assertStringContainsString('Indexed 2 notes.', $output);
        $this->assertStringContainsString('Indexing tasks...', $output);
        $this->assertStringContainsString('Indexed 3 tasks.', $output);
    }

    private function createMockNote(int $id, string $title): Note
    {
        $note = $this->createMock(Note::class);
        $note->method('getId')->willReturn($id);
        $note->method('getTitle')->willReturn($title);

        return $note;
    }

    private function createMockTask(int $id, string $title): Task
    {
        $task = $this->createMock(Task::class);
        $task->method('getId')->willReturn($id);
        $task->method('getTitle')->willReturn($title);

        return $task;
    }
}
