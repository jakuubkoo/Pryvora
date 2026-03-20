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
        return new Client([
            'hosts' => [
                $this->elasticsearch_host,
            ],
        ]);
    }
}
