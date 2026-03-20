<?php

declare(strict_types=1);

namespace App\Tests\Service\Search;

use App\Entity\Note;
use App\Entity\Tag;
use App\Entity\User;
use App\Service\Search\ElasticsearchClientFactory;
use App\Service\Search\SearchIndexer;
use Elastica\Client;
use Elastica\Document;
use Elastica\Exception\ClientException;
use Elastica\Index;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

#[AllowMockObjectsWithoutExpectations]
class SearchIndexerTest extends TestCase
{
    /** @var MockObject&ElasticsearchClientFactory */
    private MockObject $factoryMock;
    /** @var MockObject&Client */
    private MockObject $clientMock;
    /** @var MockObject&Index */
    private MockObject $indexMock;
    /** @var MockObject&LoggerInterface */
    private MockObject $loggerMock;
    private SearchIndexer $searchIndexer;

    protected function setUp(): void
    {
        $this->factoryMock = $this->createMock(ElasticsearchClientFactory::class);
        $this->clientMock = $this->createMock(Client::class);
        $this->indexMock = $this->createMock(Index::class);
        $this->loggerMock = $this->createMock(LoggerInterface::class);

        $this->factoryMock->method('create')->willReturn($this->clientMock);
        $this->clientMock->method('getIndex')->willReturn($this->indexMock);

        $this->searchIndexer = new SearchIndexer(
            $this->factoryMock,
            $this->loggerMock,
            'pryvora_test'
        );
    }

    public function test_index_note_success(): void
    {
        $note = $this->createNote(1, 'Test Note', 'Test content');

        $this->indexMock->expects($this->once())
            ->method('addDocument')
            ->with($this->callback(static function (Document $doc) {
                return $doc->getId() === 'note_1'
                    && $doc->get('type') === 'note'
                    && $doc->get('title') === 'Test Note';
            }));

        $this->indexMock->expects($this->once())
            ->method('refresh');

        $this->searchIndexer->indexNote($note);
    }

    public function test_index_note_logs_error_on_failure(): void
    {
        $note = $this->createNote(1, 'Test Note', 'Test content');

        $exception = new ClientException('Test error');

        $this->indexMock->expects($this->once())
            ->method('addDocument')
            ->willThrowException($exception);

        $this->loggerMock->expects($this->once())
            ->method('error')
            ->with('Elasticsearch indexing failed for note', [
                'error' => 'Test error',
                'type' => ClientException::class,
                'note_id' => 1,
            ]);

        $this->searchIndexer->indexNote($note);
    }

    public function test_index_task_success(): void
    {
        $task = $this->createTask(1, 'Test Task');

        $this->indexMock->expects($this->once())
            ->method('addDocument')
            ->with($this->callback(static function (Document $doc) {
                return $doc->getId() === 'task_1'
                    && $doc->get('type') === 'task'
                    && $doc->get('title') === 'Test Task';
            }));

        $this->indexMock->expects($this->once())
            ->method('refresh');

        $this->searchIndexer->indexTask($task);
    }

    public function test_index_task_logs_error_on_failure(): void
    {
        $task = $this->createTask(1, 'Test Task');

        $exception = new ClientException('Test error');

        $this->indexMock->expects($this->once())
            ->method('addDocument')
            ->willThrowException($exception);

        $this->loggerMock->expects($this->once())
            ->method('error')
            ->with('Elasticsearch indexing failed for task', [
                'error' => 'Test error',
                'type' => ClientException::class,
                'task_id' => 1,
            ]);

        $this->searchIndexer->indexTask($task);
    }

    public function test_delete_note_success(): void
    {
        $note = $this->createNote(1, 'Test Note', 'Test content');

        $this->indexMock->expects($this->once())
            ->method('deleteById')
            ->with('note_1');

        $this->indexMock->expects($this->once())
            ->method('refresh');

        $this->searchIndexer->deleteNote($note);
    }

    public function test_delete_note_logs_error_on_failure(): void
    {
        $note = $this->createNote(1, 'Test Note', 'Test content');

        $exception = new ClientException('Test error');

        $this->indexMock->expects($this->once())
            ->method('deleteById')
            ->willThrowException($exception);

        $this->loggerMock->expects($this->once())
            ->method('error')
            ->with('Elasticsearch delete failed for note', [
                'error' => 'Test error',
                'type' => ClientException::class,
                'note_id' => 1,
            ]);

        $this->searchIndexer->deleteNote($note);
    }

    public function test_delete_task_success(): void
    {
        $task = $this->createTask(1, 'Test Task');

        $this->indexMock->expects($this->once())
            ->method('deleteById')
            ->with('task_1');

        $this->indexMock->expects($this->once())
            ->method('refresh');

        $this->searchIndexer->deleteTask($task);
    }

    public function test_delete_task_logs_error_on_failure(): void
    {
        $task = $this->createTask(1, 'Test Task');

        $exception = new ClientException('Test error');

        $this->indexMock->expects($this->once())
            ->method('deleteById')
            ->willThrowException($exception);

        $this->loggerMock->expects($this->once())
            ->method('error')
            ->with('Elasticsearch delete failed for task', [
                'error' => 'Test error',
                'type' => ClientException::class,
                'task_id' => 1,
            ]);

        $this->searchIndexer->deleteTask($task);
    }

    private function createNote(int $id, string $title, string $content): Note
    {
        $user = new User();
        $reflection = new \ReflectionClass($user);
        $idProperty = $reflection->getProperty('id');
        $idProperty->setAccessible(true);
        $idProperty->setValue($user, $id);
        
        $emailProperty = $reflection->getProperty('email');
        $emailProperty->setAccessible(true);
        $emailProperty->setValue($user, 'test@example.com');
        
        $firstNameProperty = $reflection->getProperty('firstName');
        $firstNameProperty->setAccessible(true);
        $firstNameProperty->setValue($user, 'Test');
        
        $lastNameProperty = $reflection->getProperty('lastName');
        $lastNameProperty->setAccessible(true);
        $lastNameProperty->setValue($user, 'User');
        
        $passwordProperty = $reflection->getProperty('password');
        $passwordProperty->setAccessible(true);
        $passwordProperty->setValue($user, 'password');

        $note = new Note();
        $noteReflection = new \ReflectionClass($note);
        
        $noteIdProperty = $noteReflection->getProperty('id');
        $noteIdProperty->setAccessible(true);
        $noteIdProperty->setValue($note, $id);
        
        $note->setTitle($title);
        $note->setContent($content);
        $note->setUser($user);
        
        $createdAtProperty = $noteReflection->getProperty('createdAt');
        $createdAtProperty->setAccessible(true);
        $createdAtProperty->setValue($note, new \DateTimeImmutable());

        return $note;
    }

    private function createTask(int $id, string $title): \App\Entity\Task
    {
        $user = new User();
        $reflection = new \ReflectionClass($user);
        $idProperty = $reflection->getProperty('id');
        $idProperty->setAccessible(true);
        $idProperty->setValue($user, $id);
        
        $emailProperty = $reflection->getProperty('email');
        $emailProperty->setAccessible(true);
        $emailProperty->setValue($user, 'test@example.com');
        
        $firstNameProperty = $reflection->getProperty('firstName');
        $firstNameProperty->setAccessible(true);
        $firstNameProperty->setValue($user, 'Test');
        
        $lastNameProperty = $reflection->getProperty('lastName');
        $lastNameProperty->setAccessible(true);
        $lastNameProperty->setValue($user, 'User');
        
        $passwordProperty = $reflection->getProperty('password');
        $passwordProperty->setAccessible(true);
        $passwordProperty->setValue($user, 'password');

        $task = new \App\Entity\Task();
        $taskReflection = new \ReflectionClass($task);
        
        $taskIdProperty = $taskReflection->getProperty('id');
        $taskIdProperty->setAccessible(true);
        $taskIdProperty->setValue($task, $id);
        
        $task->setTitle($title);
        $task->setAuthor($user);
        $task->setStatus(\App\Enum\TaskStatus::TODO);
        $task->setPriority(\App\Enum\TaskPriority::MEDIUM);
        
        $createdAtProperty = $taskReflection->getProperty('createdAt');
        $createdAtProperty->setAccessible(true);
        $createdAtProperty->setValue($task, new \DateTimeImmutable());

        return $task;
    }
}
