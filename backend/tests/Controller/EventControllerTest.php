<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

class EventControllerTest extends WebTestCase
{
    private static string $test_password = 'TestPassword123!';

    private function register_and_login_user(): array
    {
        $client = static::createClient();
        $email = 'event_test_' . uniqid() . '@example.com';

        // Register
        $client->request('POST', '/api/auth/register', [], [], ['CONTENT_TYPE' => 'application/json'], json_encode([
            'firstName' => 'John',
            'lastName' => 'Doe',
            'email' => $email,
            'password' => self::$test_password,
            'confirmPassword' => self::$test_password,
        ]) ?: '');

        // Login
        $client->request('POST', '/api/auth/login', [], [], ['CONTENT_TYPE' => 'application/json'], json_encode([
            'email' => $email,
            'password' => self::$test_password,
        ]) ?: '');

        $response_data = json_decode($client->getResponse()->getContent() ?: '', true);
        $token = $response_data['token'] ?? '';

        return ['client' => $client, 'token' => $token, 'email' => $email];
    }

    public function test_create_event(): void
    {
        $auth_data = $this->register_and_login_user();
        $client = $auth_data['client'];
        $token = $auth_data['token'];

        $startsAt = (new \DateTimeImmutable())->modify('+1 day')->setTime(10, 0)->format(\DateTimeInterface::ATOM);
        $endsAt = (new \DateTimeImmutable())->modify('+1 day')->setTime(11, 0)->format(\DateTimeInterface::ATOM);
        $reminderAt = (new \DateTimeImmutable())->modify('+1 day')->setTime(9, 0)->format(\DateTimeInterface::ATOM);

        $client->request('POST', '/api/event', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'title' => 'Test Event ' . uniqid(),
            'description' => 'This is a test event description',
            'location' => 'Test Location',
            'startsAt' => $startsAt,
            'endsAt' => $endsAt,
            'allDay' => false,
            'reminderAt' => $reminderAt,
        ]) ?: '');

        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $response_data = json_decode($client->getResponse()->getContent() ?: '', true);
        $this->assertArrayHasKey('id', $response_data);
        $this->assertArrayHasKey('title', $response_data);
        $this->assertArrayHasKey('description', $response_data);
        $this->assertArrayHasKey('location', $response_data);
        $this->assertArrayHasKey('starts_at', $response_data);
        $this->assertArrayHasKey('ends_at', $response_data);
        $this->assertArrayHasKey('all_day', $response_data);
        $this->assertArrayHasKey('reminder_at', $response_data);
        $this->assertEquals('Test Location', $response_data['location']);
        $this->assertFalse($response_data['all_day']);
    }

    public function test_create_event_without_authentication(): void
    {
        $client = static::createClient();

        $startsAt = (new \DateTimeImmutable())->modify('+1 day')->setTime(10, 0)->format(\DateTimeInterface::ATOM);
        $endsAt = (new \DateTimeImmutable())->modify('+1 day')->setTime(11, 0)->format(\DateTimeInterface::ATOM);

        $client->request('POST', '/api/event', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'title' => 'Test Event',
            'startsAt' => $startsAt,
            'endsAt' => $endsAt,
        ]) ?: '');

        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }

    public function test_create_event_with_invalid_data(): void
    {
        $auth_data = $this->register_and_login_user();
        $client = $auth_data['client'];
        $token = $auth_data['token'];

        // Test with title too short
        $startsAt = (new \DateTimeImmutable())->modify('+1 day')->setTime(10, 0)->format(\DateTimeInterface::ATOM);
        $endsAt = (new \DateTimeImmutable())->modify('+1 day')->setTime(11, 0)->format(\DateTimeInterface::ATOM);

        $client->request('POST', '/api/event', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'title' => 'AB',
            'startsAt' => $startsAt,
            'endsAt' => $endsAt,
        ]) ?: '');

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $response_data = json_decode($client->getResponse()->getContent() ?: '', true);
        $this->assertArrayHasKey('errors', $response_data);
    }

    public function test_create_event_with_invalid_date_format(): void
    {
        $auth_data = $this->register_and_login_user();
        $client = $auth_data['client'];
        $token = $auth_data['token'];

        $client->request('POST', '/api/event', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'title' => 'Test Event',
            'startsAt' => 'invalid-date',
            'endsAt' => 'invalid-date',
        ]) ?: '');

        // DTO validation returns 422 for invalid date format
        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $response_data = json_decode($client->getResponse()->getContent() ?: '', true);
        $this->assertArrayHasKey('errors', $response_data);
    }

    public function test_create_event_with_invalid_reminder_date_format(): void
    {
        $auth_data = $this->register_and_login_user();
        $client = $auth_data['client'];
        $token = $auth_data['token'];

        $startsAt = (new \DateTimeImmutable())->modify('+1 day')->setTime(10, 0)->format(\DateTimeInterface::ATOM);
        $endsAt = (new \DateTimeImmutable())->modify('+1 day')->setTime(11, 0)->format(\DateTimeInterface::ATOM);

        $client->request('POST', '/api/event', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'title' => 'Test Event',
            'startsAt' => $startsAt,
            'endsAt' => $endsAt,
            'reminderAt' => 'invalid-reminder-date',
        ]) ?: '');

        // DTO validation returns 422 for invalid date format
        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $response_data = json_decode($client->getResponse()->getContent() ?: '', true);
        $this->assertArrayHasKey('errors', $response_data);
    }

    public function test_create_event_with_starts_at_after_ends_at(): void
    {
        $auth_data = $this->register_and_login_user();
        $client = $auth_data['client'];
        $token = $auth_data['token'];

        $startsAt = (new \DateTimeImmutable())->modify('+2 days')->setTime(10, 0)->format(\DateTimeInterface::ATOM);
        $endsAt = (new \DateTimeImmutable())->modify('+1 day')->setTime(11, 0)->format(\DateTimeInterface::ATOM);

        $client->request('POST', '/api/event', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'title' => 'Test Event',
            'startsAt' => $startsAt,
            'endsAt' => $endsAt,
        ]) ?: '');

        $this->assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
        $response_data = json_decode($client->getResponse()->getContent() ?: '', true);
        $this->assertArrayHasKey('error', $response_data);
        $this->assertEquals('Starts at must be before ends at', $response_data['error']);
    }

    public function test_create_event_without_reminder(): void
    {
        $auth_data = $this->register_and_login_user();
        $client = $auth_data['client'];
        $token = $auth_data['token'];

        $startsAt = (new \DateTimeImmutable())->modify('+1 day')->setTime(10, 0)->format(\DateTimeInterface::ATOM);
        $endsAt = (new \DateTimeImmutable())->modify('+1 day')->setTime(11, 0)->format(\DateTimeInterface::ATOM);

        $client->request('POST', '/api/event', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'title' => 'Test Event Without Reminder',
            'startsAt' => $startsAt,
            'endsAt' => $endsAt,
            'allDay' => true,
        ]) ?: '');

        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $response_data = json_decode($client->getResponse()->getContent() ?: '', true);
        $this->assertArrayHasKey('id', $response_data);
        $this->assertNull($response_data['reminder_at']);
        $this->assertTrue($response_data['all_day']);
    }

    public function test_fetch_user_events(): void
    {
        $auth_data = $this->register_and_login_user();
        $client = $auth_data['client'];
        $token = $auth_data['token'];

        // Create an event first
        $startsAt = (new \DateTimeImmutable())->modify('+1 day')->setTime(10, 0)->format(\DateTimeInterface::ATOM);
        $endsAt = (new \DateTimeImmutable())->modify('+1 day')->setTime(11, 0)->format(\DateTimeInterface::ATOM);

        $client->request('POST', '/api/event', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'title' => 'Test Event ' . uniqid(),
            'startsAt' => $startsAt,
            'endsAt' => $endsAt,
        ]) ?: '');

        // Fetch events (without trailing slash)
        $client->request('GET', '/api/event', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
        ]);

        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
        $response_data = json_decode($client->getResponse()->getContent() ?: '', true);
        $this->assertIsArray($response_data);
        $this->assertGreaterThan(0, count($response_data));
    }

    public function test_fetch_events_without_authentication(): void
    {
        $client = static::createClient();

        $client->request('GET', '/api/event/');

        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }

    public function test_fetch_events_with_date_range(): void
    {
        $auth_data = $this->register_and_login_user();
        $client = $auth_data['client'];
        $token = $auth_data['token'];

        $from = (new \DateTimeImmutable())->modify('-1 day')->format(\DateTimeInterface::ATOM);
        $to = (new \DateTimeImmutable())->modify('+30 days')->format(\DateTimeInterface::ATOM);

        $client->request('GET', '/api/event?from=' . urlencode($from) . '&to=' . urlencode($to), [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
        ]);

        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
    }

    public function test_fetch_events_with_invalid_date_range(): void
    {
        $auth_data = $this->register_and_login_user();
        $client = $auth_data['client'];
        $token = $auth_data['token'];

        $client->request('GET', '/api/event?from=invalid&to=invalid', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
        ]);

        // Invalid date format returns 400 from controller date parsing
        $this->assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
    }

    public function test_update_event(): void
    {
        $auth_data = $this->register_and_login_user();
        $client = $auth_data['client'];
        $token = $auth_data['token'];

        // Create an event first
        $startsAt = (new \DateTimeImmutable())->modify('+1 day')->setTime(10, 0)->format(\DateTimeInterface::ATOM);
        $endsAt = (new \DateTimeImmutable())->modify('+1 day')->setTime(11, 0)->format(\DateTimeInterface::ATOM);

        $client->request('POST', '/api/event', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'title' => 'Original Event ' . uniqid(),
            'startsAt' => $startsAt,
            'endsAt' => $endsAt,
        ]) ?: '');

        $create_response = json_decode($client->getResponse()->getContent() ?: '', true);
        $event_id = $create_response['id'];

        $newStartsAt = (new \DateTimeImmutable())->modify('+2 days')->setTime(14, 0)->format(\DateTimeInterface::ATOM);
        $newEndsAt = (new \DateTimeImmutable())->modify('+2 days')->setTime(15, 0)->format(\DateTimeInterface::ATOM);
        $newReminderAt = (new \DateTimeImmutable())->modify('+2 days')->setTime(13, 0)->format(\DateTimeInterface::ATOM);

        // Update the event
        $client->request('PATCH', '/api/event/' . $event_id, [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'title' => 'Updated Event Title',
            'description' => 'Updated description',
            'location' => 'New Location',
            'startsAt' => $newStartsAt,
            'endsAt' => $newEndsAt,
            'allDay' => true,
            'reminderAt' => $newReminderAt,
        ]) ?: '');

        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
        $response_data = json_decode($client->getResponse()->getContent() ?: '', true);
        $this->assertArrayHasKey('id', $response_data);
        $this->assertArrayHasKey('title', $response_data);
        $this->assertEquals('Updated Event Title', $response_data['title']);
        $this->assertEquals('Updated description', $response_data['description']);
        $this->assertEquals('New Location', $response_data['location']);
        $this->assertTrue($response_data['all_day']);
    }

    public function test_update_event_not_found(): void
    {
        $auth_data = $this->register_and_login_user();
        $client = $auth_data['client'];
        $token = $auth_data['token'];

        // Try to update a non-existent event
        $client->request('PATCH', '/api/event/999999', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'title' => 'Updated Title',
        ]) ?: '');

        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        $response_data = json_decode($client->getResponse()->getContent() ?: '', true);
        $this->assertArrayHasKey('error', $response_data);
        $this->assertEquals('Event not found', $response_data['error']);
    }

    public function test_update_event_with_invalid_reminder_date_format(): void
    {
        $auth_data = $this->register_and_login_user();
        $client = $auth_data['client'];
        $token = $auth_data['token'];

        // Create an event first
        $startsAt = (new \DateTimeImmutable())->modify('+1 day')->setTime(10, 0)->format(\DateTimeInterface::ATOM);
        $endsAt = (new \DateTimeImmutable())->modify('+1 day')->setTime(11, 0)->format(\DateTimeInterface::ATOM);

        $client->request('POST', '/api/event', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'title' => 'Test Event',
            'startsAt' => $startsAt,
            'endsAt' => $endsAt,
        ]) ?: '');

        $create_response = json_decode($client->getResponse()->getContent() ?: '', true);
        $event_id = $create_response['id'];

        // Update with invalid reminder date - must include title for validation
        $client->request('PATCH', '/api/event/' . $event_id, [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'title' => 'Test Event',
            'reminderAt' => 'invalid-reminder-date',
        ]) ?: '');

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $response_data = json_decode($client->getResponse()->getContent() ?: '', true);
        $this->assertArrayHasKey('errors', $response_data);
    }

    public function test_update_event_remove_reminder(): void
    {
        $auth_data = $this->register_and_login_user();
        $client = $auth_data['client'];
        $token = $auth_data['token'];

        $reminderAt = (new \DateTimeImmutable())->modify('+1 day')->setTime(9, 0)->format(\DateTimeInterface::ATOM);
        $startsAt = (new \DateTimeImmutable())->modify('+1 day')->setTime(10, 0)->format(\DateTimeInterface::ATOM);
        $endsAt = (new \DateTimeImmutable())->modify('+1 day')->setTime(11, 0)->format(\DateTimeInterface::ATOM);

        // Create an event with reminder
        $client->request('POST', '/api/event', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'title' => 'Event With Reminder',
            'startsAt' => $startsAt,
            'endsAt' => $endsAt,
            'reminderAt' => $reminderAt,
        ]) ?: '');

        $create_response = json_decode($client->getResponse()->getContent() ?: '', true);
        $event_id = $create_response['id'];

        // Update to remove reminder - must include title and dates for validation
        $client->request('PATCH', '/api/event/' . $event_id, [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'title' => 'Event With Reminder',
            'startsAt' => $startsAt,
            'endsAt' => $endsAt,
            'reminderAt' => null,
        ]) ?: '');

        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
        $response_data = json_decode($client->getResponse()->getContent() ?: '', true);
        $this->assertNull($response_data['reminder_at']);
    }

    public function test_delete_event(): void
    {
        $auth_data = $this->register_and_login_user();
        $client = $auth_data['client'];
        $token = $auth_data['token'];

        // Create an event first
        $startsAt = (new \DateTimeImmutable())->modify('+1 day')->setTime(10, 0)->format(\DateTimeInterface::ATOM);
        $endsAt = (new \DateTimeImmutable())->modify('+1 day')->setTime(11, 0)->format(\DateTimeInterface::ATOM);

        $client->request('POST', '/api/event', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'title' => 'Event to Delete ' . uniqid(),
            'startsAt' => $startsAt,
            'endsAt' => $endsAt,
        ]) ?: '');

        $create_response = json_decode($client->getResponse()->getContent() ?: '', true);
        $event_id = $create_response['id'];

        // Delete the event
        $client->request('DELETE', '/api/event/' . $event_id, [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
        ]);

        $this->assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);
    }

    public function test_delete_event_not_found(): void
    {
        $auth_data = $this->register_and_login_user();
        $client = $auth_data['client'];
        $token = $auth_data['token'];

        // Try to delete a non-existent event
        $client->request('DELETE', '/api/event/999999', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
        ]);

        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        $response_data = json_decode($client->getResponse()->getContent() ?: '', true);
        $this->assertArrayHasKey('error', $response_data);
        $this->assertEquals('Event not found', $response_data['error']);
    }

    public function test_cannot_access_another_users_event(): void
    {
        // Create first user and event
        $email1 = 'user1_' . uniqid() . '@example.com';
        $client = static::createClient();
        
        $client->request('POST', '/api/auth/register', [], [], ['CONTENT_TYPE' => 'application/json'], json_encode([
            'firstName' => 'User',
            'lastName' => 'One',
            'email' => $email1,
            'password' => self::$test_password,
            'confirmPassword' => self::$test_password,
        ]) ?: '');
        
        $client->request('POST', '/api/auth/login', [], [], ['CONTENT_TYPE' => 'application/json'], json_encode([
            'email' => $email1,
            'password' => self::$test_password,
        ]) ?: '');
        
        $token1 = json_decode($client->getResponse()->getContent() ?: '', true)['token'] ?? '';

        $startsAt = (new \DateTimeImmutable())->modify('+1 day')->setTime(10, 0)->format(\DateTimeInterface::ATOM);
        $endsAt = (new \DateTimeImmutable())->modify('+1 day')->setTime(11, 0)->format(\DateTimeInterface::ATOM);

        $client->request('POST', '/api/event', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token1,
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'title' => 'Private Event',
            'startsAt' => $startsAt,
            'endsAt' => $endsAt,
        ]) ?: '');

        $create_response = json_decode($client->getResponse()->getContent() ?: '', true);
        $event_id = $create_response['id'];

        // Create second user
        $email2 = 'user2_' . uniqid() . '@example.com';
        
        $client->request('POST', '/api/auth/register', [], [], ['CONTENT_TYPE' => 'application/json'], json_encode([
            'firstName' => 'User',
            'lastName' => 'Two',
            'email' => $email2,
            'password' => self::$test_password,
            'confirmPassword' => self::$test_password,
        ]) ?: '');
        
        $client->request('POST', '/api/auth/login', [], [], ['CONTENT_TYPE' => 'application/json'], json_encode([
            'email' => $email2,
            'password' => self::$test_password,
        ]) ?: '');
        
        $token2 = json_decode($client->getResponse()->getContent() ?: '', true)['token'] ?? '';

        // Try to update first user's event
        $client->request('PATCH', '/api/event/' . $event_id, [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token2,
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'title' => 'Hacked Title',
        ]) ?: '');

        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);

        // Try to delete first user's event
        $client->request('DELETE', '/api/event/' . $event_id, [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token2,
        ]);

        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }
}
