<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

class TagControllerTest extends WebTestCase
{
    private static string $test_password = 'TestPassword123!';

    private function register_and_login_user(): array
    {
        $client = static::createClient();
        $email = 'tag_test_' . uniqid() . '@example.com';

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

    public function test_create_tag(): void
    {
        $auth_data = $this->register_and_login_user();
        $client = $auth_data['client'];
        $token = $auth_data['token'];

        $client->request('POST', '/api/tag/', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'name' => 'Test Tag ' . uniqid(),
            'color' => '#FF5733',
        ]) ?: '');

        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $response_data = json_decode($client->getResponse()->getContent() ?: '', true);
        $this->assertArrayHasKey('id', $response_data);
        $this->assertArrayHasKey('name', $response_data);
        $this->assertArrayHasKey('color', $response_data);
    }

    public function test_create_tag_without_authentication(): void
    {
        $client = static::createClient();

        $client->request('POST', '/api/tag/', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'name' => 'Test Tag',
            'color' => '#FF5733',
        ]) ?: '');

        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }

    public function test_fetch_user_tags(): void
    {
        $auth_data = $this->register_and_login_user();
        $client = $auth_data['client'];
        $token = $auth_data['token'];

        // Create a tag first
        $client->request('POST', '/api/tag/', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'name' => 'Test Tag ' . uniqid(),
            'color' => '#FF5733',
        ]) ?: '');

        // Fetch tags
        $client->request('GET', '/api/tag/', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
        ]);

        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
        $response_data = json_decode($client->getResponse()->getContent() ?: '', true);
        $this->assertIsArray($response_data);
    }

    public function test_update_tag(): void
    {
        $auth_data = $this->register_and_login_user();
        $client = $auth_data['client'];
        $token = $auth_data['token'];

        // Create a tag first
        $client->request('POST', '/api/tag/', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'name' => 'Original Tag ' . uniqid(),
            'color' => '#FF5733',
        ]) ?: '');

        $create_response = json_decode($client->getResponse()->getContent() ?: '', true);
        $tag_id = $create_response['id'];

        // Update the tag
        $client->request('PATCH', '/api/tag/' . $tag_id, [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'name' => 'Updated Tag',
            'color' => '#00FF00',
        ]) ?: '');

        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
        $response_data = json_decode($client->getResponse()->getContent() ?: '', true);
        $this->assertArrayHasKey('id', $response_data);
        $this->assertArrayHasKey('name', $response_data);
        $this->assertEquals('Updated Tag', $response_data['name']);
    }

    public function test_delete_tag(): void
    {
        $auth_data = $this->register_and_login_user();
        $client = $auth_data['client'];
        $token = $auth_data['token'];

        // Create a tag first
        $client->request('POST', '/api/tag/', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'name' => 'Tag to Delete ' . uniqid(),
            'color' => '#FF5733',
        ]) ?: '');

        $create_response = json_decode($client->getResponse()->getContent() ?: '', true);
        $tag_id = $create_response['id'];

        // Delete the tag
        $client->request('DELETE', '/api/tag/' . $tag_id, [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
        ]);

        $this->assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);
    }
}

