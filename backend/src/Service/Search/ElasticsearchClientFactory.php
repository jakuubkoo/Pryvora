<?php

declare(strict_types=1);

namespace App\Service\Search;

use Elastica\Client;

class ElasticsearchClientFactory
{
    public function __construct(
        private readonly string $elasticsearchUrl,
    ) {
    }

    public function create(): Client
    {
        return new Client($this->elasticsearchUrl);
    }
}
