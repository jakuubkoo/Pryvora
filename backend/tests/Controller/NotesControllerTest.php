<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

class NotesControllerTest extends WebTestCase
{
    private static string $test_password = 'TestPassword123!';

    private function register_and_login_user(): array
    {
        $client = static::createClient();
        $email = 'notes_test_' . uniqid() . '@example.com';

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

    public function test_create_note(): void
    {
        $auth_data = $this->register_and_login_user();
        $client = $auth_data['client'];
        $token = $auth_data['token'];

        $client->request('POST', '/api/notes/', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'title' => 'Test Note ' . uniqid(),
            'content' => 'This is a test note content',
        ]) ?: '');

        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $response_data = json_decode($client->getResponse()->getContent() ?: '', true);
        $this->assertArrayHasKey('message', $response_data);
        $this->assertArrayHasKey('id', $response_data);
        $this->assertEquals('Note created', $response_data['message']);
    }

    public function test_create_note_without_authentication(): void
    {
        $client = static::createClient();

        $client->request('POST', '/api/notes/', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'title' => 'Test Note',
            'content' => 'This is a test note content',
        ]) ?: '');

        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }

    public function test_fetch_user_notes(): void
    {
        $auth_data = $this->register_and_login_user();
        $client = $auth_data['client'];
        $token = $auth_data['token'];

        // Create a note first
        $client->request('POST', '/api/notes/', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'title' => 'Test Note ' . uniqid(),
            'content' => 'This is a test note content',
        ]) ?: '');

        // Fetch notes
        $client->request('GET', '/api/notes/', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
        ]);

        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
        $response_data = json_decode($client->getResponse()->getContent() ?: '', true);
        $this->assertArrayHasKey('notes', $response_data);
        $this->assertIsArray($response_data['notes']);
        $this->assertGreaterThan(0, count($response_data['notes']));
    }

    public function test_fetch_notes_without_authentication(): void
    {
        $client = static::createClient();

        $client->request('GET', '/api/notes/');

        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }

    public function test_update_note(): void
    {
        $auth_data = $this->register_and_login_user();
        $client = $auth_data['client'];
        $token = $auth_data['token'];

        // Create a note first
        $client->request('POST', '/api/notes/', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'title' => 'Original Title ' . uniqid(),
            'content' => 'Original content',
        ]) ?: '');

        $create_response = json_decode($client->getResponse()->getContent() ?: '', true);
        $note_id = $create_response['id'];

        // Update the note
        $client->request('PUT', '/api/notes/' . $note_id, [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'title' => 'Updated Title',
            'content' => 'Updated content',
        ]) ?: '');

        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
        $response_data = json_decode($client->getResponse()->getContent() ?: '', true);
        $this->assertArrayHasKey('message', $response_data);
        $this->assertEquals('Note updated', $response_data['message']);
    }

    public function test_delete_note(): void
    {
        $auth_data = $this->register_and_login_user();
        $client = $auth_data['client'];
        $token = $auth_data['token'];

        // Create a note first
        $client->request('POST', '/api/notes/', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'title' => 'Note to Delete ' . uniqid(),
            'content' => 'This note will be deleted',
        ]) ?: '');

        $create_response = json_decode($client->getResponse()->getContent() ?: '', true);
        $note_id = $create_response['id'];

        // Delete the note
        $client->request('DELETE', '/api/notes/' . $note_id, [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
        ]);

        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
        $response_data = json_decode($client->getResponse()->getContent() ?: '', true);
        $this->assertArrayHasKey('message', $response_data);
        $this->assertEquals('Note deleted', $response_data['message']);
    }

    public function test_create_note_with_invalid_data(): void
    {
        $auth_data = $this->register_and_login_user();
        $client = $auth_data['client'];
        $token = $auth_data['token'];

        $client->request('POST', '/api/notes/', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'title' => '',
            'content' => '',
        ]) ?: '');

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $response_data = json_decode($client->getResponse()->getContent() ?: '', true);
        $this->assertArrayHasKey('errors', $response_data);
    }

    public function test_get_single_note(): void
    {
        $auth_data = $this->register_and_login_user();
        $client = $auth_data['client'];
        $token = $auth_data['token'];

        // Create a note first
        $client->request('POST', '/api/notes/', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'title' => 'Single Note ' . uniqid(),
            'content' => 'Content for single note',
        ]) ?: '');

        $create_response = json_decode($client->getResponse()->getContent() ?: '', true);
        $note_id = $create_response['id'];

        // Get the note
        $client->request('GET', '/api/notes/' . $note_id, [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
        ]);

        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
        $response_data = json_decode($client->getResponse()->getContent() ?: '', true);
        $this->assertArrayHasKey('id', $response_data);
        $this->assertArrayHasKey('title', $response_data);
        $this->assertArrayHasKey('content', $response_data);
        $this->assertEquals($note_id, $response_data['id']);
    }
}
