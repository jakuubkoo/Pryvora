<?php

declare(strict_types=1);

namespace App\Service\Search;

use Elastica\Client;

class ElasticsearchClientFactory
{
    public function __construct(
        private readonly string $elasticsearch_host,
    ) {
    }

    public function create(): Client
    {
        // Return a client with empty hosts if elasticsearch_host is empty (for tests)
        // This allows tests to run without requiring an actual Elasticsearch instance
        if (empty($this->elasticsearch_host)) {
            return new Client(['hosts' => []]);
        }

        return new Client([
            'hosts' => [
                $this->elasticsearch_host,
            ],
        ]);
    }
}
