<?php

declare(strict_types=1);

namespace App\Service\Search;

use App\Entity\User;
use Elastica\Query;
use Elastica\Query\BoolQuery;
use Elastica\Query\Term;
use Elastica\Query\Wildcard;

class SearchService
{
    private const INDEX_NAME = 'pryvora_search';

    private ElasticsearchClientFactory $elasticsearchClientFactory;

    public function __construct(ElasticsearchClientFactory $elasticsearchClientFactory)
    {
        $this->elasticsearchClientFactory = $elasticsearchClientFactory;
    }

    /**
     * Executes a search query against an Elasticsearch index.
     *
     * @param string $query the search query string
     * @param User   $user  the user for whom the query is being executed
     *
     * @return array<array{id: string, type: string, title: string}> an array of search results, each containing the document ID, type, and title
     */
    public function search(string $query, User $user): array
    {
        $client = $this->elasticsearchClientFactory->create()->getIndex(self::INDEX_NAME);

        // Wildcard query for partial matching (supports searching anywhere in the text)
        $wildcard = new Wildcard('title', '*'.$query.'*');

        $userFilter = new Term();
        $userFilter->setTerm('user_id', $user->getId() ?: 0);

        $bool = new BoolQuery();
        $bool->addMust($wildcard);
        $bool->addFilter($userFilter);

        try {
            $results = $client->search(new Query($bool));
        } catch (\Exception $e) {
            return [];
        }

        $hits = [];
        foreach ($results->getDocuments() as $document) {
            $data = $document->getData();
            $hits[] = [
                'id' => (string) $document->getId(),
                'type' => (string) ($data['type'] ?? 'unknownType'),
                'title' => (string) ($data['title'] ?? 'nullTitle'),
            ];
        }

        return $hits;
    }
}
