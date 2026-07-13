<?php

declare(strict_types=1);

namespace App\Tests\Integration\Google;

use App\Integration\Google\GmailClient;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

/**
 * Uses MockHttpClient — nothing here reaches Google.
 */
class GmailClientPacingTest extends TestCase
{
    /**
     * An unpaced loop hammers Gmail as fast as the network allows, which is both
     * rude and indistinguishable from a runaway bug. Six requests must therefore
     * take at least five gaps of 200ms.
     */
    public function testRequestsAreSpacedAtAboutFivePerSecond(): void
    {
        $responses = array_fill(0, 6, new MockResponse(
            json_encode(['emailAddress' => 'me@example.com', 'historyId' => '1', 'messagesTotal' => 0]),
            ['http_code' => 200],
        ));

        $client = new GmailClient(new MockHttpClient($responses));
        $started = microtime(true);

        for ($i = 0; $i < 6; ++$i) {
            $client->get_profile('fake-token');
        }

        $elapsed = microtime(true) - $started;

        // 5 gaps × 200ms = 1.0s. Allow a little slack for a slow machine, but the
        // point is that it is emphatically not instant.
        $this->assertGreaterThan(0.95, $elapsed, 'Gmail requests are not being paced.');
    }

    /**
     * The pacing must not add latency that is not needed — a single request has no
     * previous request to be spaced from.
     */
    public function testTheFirstRequestIsNotDelayed(): void
    {
        $client = new GmailClient(new MockHttpClient([
            new MockResponse(json_encode(['emailAddress' => 'me@example.com', 'historyId' => '1']), ['http_code' => 200]),
        ]));

        $started = microtime(true);
        $client->get_profile('fake-token');

        $this->assertLessThan(0.1, microtime(true) - $started);
    }
}
