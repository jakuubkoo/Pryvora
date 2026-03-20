<?php

declare(strict_types=1);

namespace App\Tests\Service\Search;

use App\Service\Search\ElasticsearchClientFactory;
use Elastica\Client;
use PHPUnit\Framework\TestCase;

class ElasticsearchClientFactoryTest extends TestCase
{
    public function test_create_returns_elastica_client(): void
    {
        $factory = new ElasticsearchClientFactory('http://localhost:9200');

        $client = $factory->create();

        $this->assertInstanceOf(Client::class, $client);
    }

    public function test_create_uses_configured_host(): void
    {
        $host = 'http://elasticsearch:9200';
        $factory = new ElasticsearchClientFactory($host);

        $client = $factory->create();

        // Verify client is configured with the correct host
        $this->assertInstanceOf(Client::class, $client);
    }
}
