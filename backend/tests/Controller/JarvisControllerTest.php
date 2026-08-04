<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

/**
 * The Jarvis endpoints act on one configured account rather than on whoever is
 * logged in, so these tests register that exact account first — services.yaml
 * pins it to jarvis_test@example.com under when@test. DAMA rolls the row back
 * between tests, so the fixed address never collides.
 */
class JarvisControllerTest extends WebTestCase
{
    private const TOKEN = 'test-jarvis-token';
    private const USER_EMAIL = 'jarvis_test@example.com';

    private function client_with_jarvis_user(): KernelBrowser
    {
        $client = static::createClient();

        $client->request('POST', '/api/auth/register', [], [], ['CONTENT_TYPE' => 'application/json'], json_encode([
            'firstName' => 'Jarvis',
            'lastName' => 'User',
            'email' => self::USER_EMAIL,
            'password' => 'TestPassword123!',
            'confirmPassword' => 'TestPassword123!',
        ]) ?: '');

        return $client;
    }

    /**
     * @param array<string, mixed>|null $body
     */
    private function call(KernelBrowser $client, string $method, string $uri, ?array $body = null, ?string $token = self::TOKEN): mixed
    {
        $headers = ['CONTENT_TYPE' => 'application/json'];

        if (null !== $token) {
            $headers['HTTP_AUTHORIZATION'] = 'Bearer ' . $token;
        }

        $client->request($method, $uri, [], [], $headers, null === $body ? '' : (json_encode($body) ?: ''));

        return json_decode($client->getResponse()->getContent() ?: '', true);
    }

    public function test_missing_token_is_rejected(): void
    {
        $client = $this->client_with_jarvis_user();

        $this->call($client, 'GET', '/api/jarvis/briefing', token: null);

        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }

    public function test_wrong_token_is_rejected(): void
    {
        $client = $this->client_with_jarvis_user();

        $this->call($client, 'GET', '/api/jarvis/briefing', token: 'not-the-token');

        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }

    /**
     * The spec defaults new tasks to medium; CreateTaskDTO defaults to low, so
     * this is the assertion that catches the handler dropping its override.
     */
    public function test_task_defaults_to_medium_priority(): void
    {
        $client = $this->client_with_jarvis_user();

        $task = $this->call($client, 'POST', '/api/jarvis/tasks', ['title' => 'Call the dentist']);

        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $this->assertSame('medium', $task['priority']);
        $this->assertSame('todo', $task['status']);
        $this->assertNull($task['due_date']);
    }

    public function test_task_accepts_a_priority_and_due_date(): void
    {
        $client = $this->client_with_jarvis_user();

        $task = $this->call($client, 'POST', '/api/jarvis/tasks', [
            'title' => 'Renew the passport',
            'priority' => 'high',
            'due_date' => '2026-12-31',
        ]);

        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $this->assertSame('high', $task['priority']);
        $this->assertSame('2026-12-31', $task['due_date']);
    }

    /**
     * CreateTaskDTO::fromArray would fatal on this via TaskPriority::from(); the
     * handler has to turn it into a 422 the assistant can read and retry.
     */
    public function test_unknown_priority_is_a_validation_error(): void
    {
        $client = $this->client_with_jarvis_user();

        $this->call($client, 'POST', '/api/jarvis/tasks', ['title' => 'Something', 'priority' => 'urgent']);

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function test_unparseable_due_date_is_a_validation_error(): void
    {
        $client = $this->client_with_jarvis_user();

        $this->call($client, 'POST', '/api/jarvis/tasks', ['title' => 'Something', 'due_date' => 'next tuesday']);

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function test_blank_title_is_a_validation_error(): void
    {
        $client = $this->client_with_jarvis_user();

        $response = $this->call($client, 'POST', '/api/jarvis/tasks', ['title' => '']);

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertArrayHasKey('title', $response['errors']);
    }

    public function test_note_without_a_title_gets_one_from_its_content(): void
    {
        $client = $this->client_with_jarvis_user();

        $note = $this->call($client, 'POST', '/api/jarvis/notes', [
            'content' => "Milk and bread\nAlso coffee",
        ]);

        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $this->assertSame('Milk and bread', $note['title']);
    }

    public function test_note_keeps_an_explicit_title(): void
    {
        $client = $this->client_with_jarvis_user();

        $note = $this->call($client, 'POST', '/api/jarvis/notes', [
            'title' => 'Shopping',
            'content' => 'Milk and bread',
        ]);

        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $this->assertSame('Shopping', $note['title']);
    }

    public function test_tasks_filter_by_today(): void
    {
        $client = $this->client_with_jarvis_user();

        $this->call($client, 'POST', '/api/jarvis/tasks', [
            'title' => 'Due today',
            'due_date' => (new \DateTimeImmutable('today'))->format('Y-m-d'),
        ]);
        $this->call($client, 'POST', '/api/jarvis/tasks', ['title' => 'Due whenever']);

        $today = $this->call($client, 'GET', '/api/jarvis/tasks?filter=today');

        $this->assertResponseIsSuccessful();
        $this->assertSame(['Due today'], array_column($today, 'title'));

        $all = $this->call($client, 'GET', '/api/jarvis/tasks');

        $this->assertCount(2, $all);
    }

    public function test_unknown_filter_is_rejected(): void
    {
        $client = $this->client_with_jarvis_user();

        $this->call($client, 'GET', '/api/jarvis/tasks?filter=yesterday');

        $this->assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
    }

    public function test_briefing_separates_today_from_overdue(): void
    {
        $client = $this->client_with_jarvis_user();

        $this->call($client, 'POST', '/api/jarvis/tasks', [
            'title' => 'Due today',
            'due_date' => (new \DateTimeImmutable('today'))->format('Y-m-d'),
        ]);
        $this->call($client, 'POST', '/api/jarvis/tasks', [
            'title' => 'Long overdue',
            'due_date' => (new \DateTimeImmutable('-3 days'))->format('Y-m-d'),
        ]);

        $briefing = $this->call($client, 'GET', '/api/jarvis/briefing');

        $this->assertResponseIsSuccessful();
        $this->assertSame((new \DateTimeImmutable('today'))->format('Y-m-d'), $briefing['date']);
        $this->assertSame(['Due today'], array_column($briefing['tasks']['today'], 'title'));
        $this->assertSame(['Long overdue'], array_column($briefing['tasks']['overdue'], 'title'));
        $this->assertArrayHasKey('events', $briefing);
    }
}
