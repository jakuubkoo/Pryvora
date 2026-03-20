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

class SearchIndexer
{
    private const INDEX_NAME = 'pryvora_search';

    private ElasticsearchClientFactory $elasticsearchClientFactory;

    public function __construct(ElasticsearchClientFactory $elasticsearchClientFactory)
    {
        $this->elasticsearchClientFactory = $elasticsearchClientFactory;
    }

    private function getClient(): Client
    {
        return $this->elasticsearchClientFactory->create();
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
        $index = $this->getClient()->getIndex(self::INDEX_NAME);

        $document = new Document(
            'note_'.$note->getId(),
            [
                'type' => 'note',
                'user_id' => $note->getUser()?->getId(),
                'title' => $note->getTitle(),
                'tags' => $note->getTags()->map(static fn ($tag) => $tag->getName())->toArray(),
                'created_at' => $note->getCreatedAt(),
                'updated_at' => $note->getUpdatedAt(),
            ]
        );

        try {
            $index->addDocument($document);
            $index->refresh();
        } catch (MissingParameterException|ClientResponseException|ServerResponseException $e) {
            // Log the error
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
        $index = $this->getClient()->getIndex(self::INDEX_NAME);

        $document = new Document(
            'task_'.$task->getId(),
            [
                'type' => 'task',
                'user_id' => $task->getAuthor()?->getId(),
                'title' => $task->getTitle(),
                'status' => $task->getStatus()->value,
                'priority' => $task->getPriority()->value,
                'due_date' => $task->getDueDate(),
                'created_at' => $task->getCreatedAt(),
            ]
        );

        try {
            $index->addDocument($document);
            $index->refresh();
        } catch (MissingParameterException|ClientResponseException|ServerResponseException $e) {
            // Log the error
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
        $index = $this->getClient()->getIndex(self::INDEX_NAME);

        try {
            $index->deleteById('task_'.$task->getId());
            $index->refresh();
        } catch (MissingParameterException|ClientResponseException|ServerResponseException $e) {
            // Log the error
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
        $index = $this->getClient()->getIndex(self::INDEX_NAME);

        try {
            $index->deleteById('note_'.$note->getId());
            $index->refresh();
        } catch (MissingParameterException|ClientResponseException|ServerResponseException $e) {
            // Log the error
        }
    }
}
