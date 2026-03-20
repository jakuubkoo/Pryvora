<?php

declare(strict_types=1);

namespace App\Service\Search;

use App\Entity\Note;
use App\Entity\Task;
use Elastic\Elasticsearch\Exception\ClientResponseException;
use Elastic\Elasticsearch\Exception\MissingParameterException;
use Elastic\Elasticsearch\Exception\ServerResponseException;
use Elastica\Client;
use Elastica\Document;
use Psr\Log\LoggerInterface;

class SearchIndexer
{
    private ElasticsearchClientFactory $elasticsearchClientFactory;
    private LoggerInterface $logger;
    private string $indexName;

    public function __construct(
        ElasticsearchClientFactory $elasticsearchClientFactory,
        LoggerInterface $logger,
        string $indexName = 'pryvora_search',
    ) {
        $this->elasticsearchClientFactory = $elasticsearchClientFactory;
        $this->logger = $logger;
        $this->indexName = $indexName;
    }

    private function getClient(): Client
    {
        return $this->elasticsearchClientFactory->create();
    }

    /**
     * Creates or updates the search index with the proper mapping.
     */
    public function createIndex(): void
    {
        $index = $this->getClient()->getIndex($this->indexName);

        if (!$index->exists()) {
            $index->create([
                'settings' => [
                    'number_of_shards' => 1,
                    'number_of_replicas' => 0,
                    'analysis' => [
                        'normalizer' => [
                            'lowercase_normalizer' => [
                                'type' => 'custom',
                                'filter' => ['lowercase'],
                            ],
                        ],
                    ],
                ],
                'mappings' => [
                    'properties' => [
                        'type' => ['type' => 'keyword'],
                        'user_id' => ['type' => 'integer'],
                        'title' => [
                            'type' => 'keyword',
                            'normalizer' => 'lowercase_normalizer',
                        ],
                        'tags' => ['type' => 'keyword'],
                        'status' => ['type' => 'keyword'],
                        'priority' => ['type' => 'keyword'],
                        'due_date' => ['type' => 'date'],
                        'created_at' => ['type' => 'date'],
                        'updated_at' => ['type' => 'date'],
                    ],
                ],
            ]);
        }
    }

    /**
     * Indexes a given note into the search engine.
     *
     * This method retrieves the search index associated with the application,
     * creates a document representing the provided note, and adds it to the index.
     * Once the document is added, the index is refreshed to ensure the changes
     * are immediately available for search queries.
     *
     * @param Note $note the Note entity to be indexed
     */
    public function indexNote(Note $note): void
    {
        $this->createIndex();
        $index = $this->getClient()->getIndex($this->indexName);

        $document = new Document(
            'note_'.$note->getId(),
            [
                'type' => 'note',
                'user_id' => $note->getUser()?->getId(),
                'title' => $note->getTitle(),
                'tags' => $note->getTags()->map(static fn ($tag) => $tag->getName())->toArray(),
                'created_at' => $note->getCreatedAt()->format(\DateTimeInterface::ATOM),
                'updated_at' => $note->getUpdatedAt()?->format(\DateTimeInterface::ATOM),
            ]
        );

        try {
            $index->addDocument($document);
            $index->refresh();
        } catch (MissingParameterException|ClientResponseException|ServerResponseException $e) {
            $this->logger->error('Elasticsearch indexing failed for note', [
                'error' => $e->getMessage(),
                'type' => $e::class,
                'note_id' => $note->getId(),
            ]);
        }
    }

    /**
     * Indexes a given task into the search engine.
     *
     * This method retrieves the appropriate search index, constructs a document
     * representation of the provided task, and adds it to the search index. After
     * the document is added, the index is refreshed to make the changes
     * immediately available for search operations.
     *
     * @param Task $task the Task entity to be indexed
     */
    public function indexTask(Task $task): void
    {
        $this->createIndex();
        $index = $this->getClient()->getIndex($this->indexName);

        $document = new Document(
            'task_'.$task->getId(),
            [
                'type' => 'task',
                'user_id' => $task->getAuthor()?->getId(),
                'title' => $task->getTitle(),
                'status' => $task->getStatus()->value,
                'priority' => $task->getPriority()->value,
                'due_date' => $task->getDueDate()?->format(\DateTimeInterface::ATOM),
                'created_at' => $task->getCreatedAt()?->format(\DateTimeInterface::ATOM),
            ]
        );

        try {
            $index->addDocument($document);
            $index->refresh();
        } catch (MissingParameterException|ClientResponseException|ServerResponseException $e) {
            $this->logger->error('Elasticsearch indexing failed for task', [
                'error' => $e->getMessage(),
                'type' => $e::class,
                'task_id' => $task->getId(),
            ]);
        }
    }

    /**
     * Deletes the specified task from the search index.
     *
     * This method retrieves the search index associated with the application and
     * removes the document corresponding to the given task. After the document is
     * deleted, the index is refreshed to ensure the changes are reflected in search
     * results.
     *
     * @param Task $task the Task entity to be removed from the index
     */
    public function deleteTask(Task $task): void
    {
        $index = $this->getClient()->getIndex($this->indexName);

        try {
            $index->deleteById('task_'.$task->getId());
            $index->refresh();
        } catch (MissingParameterException|ClientResponseException|ServerResponseException $e) {
            $this->logger->error('Elasticsearch delete failed for task', [
                'error' => $e->getMessage(),
                'type' => $e::class,
                'task_id' => $task->getId(),
            ]);
        }
    }

    /**
     * Deletes the specified note from the search index.
     *
     * This method retrieves the search index associated with the application and
     * removes the document corresponding to the given note. After the document is
     * deleted, the index is refreshed to ensure the changes are reflected in search
     * results.
     *
     * @param Note $note the Note entity to be removed from the index
     */
    public function deleteNote(Note $note): void
    {
        $index = $this->getClient()->getIndex($this->indexName);

        try {
            $index->deleteById('note_'.$note->getId());
            $index->refresh();
        } catch (MissingParameterException|ClientResponseException|ServerResponseException $e) {
            $this->logger->error('Elasticsearch delete failed for note', [
                'error' => $e->getMessage(),
                'type' => $e::class,
                'note_id' => $note->getId(),
            ]);
        }
    }
}
