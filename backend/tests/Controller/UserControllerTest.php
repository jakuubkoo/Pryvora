<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

class UserControllerTest extends WebTestCase
{
    private static string $test_password = 'TestPassword123!';

    private function register_and_login_user(): array
    {
        $client = static::createClient();
        $email = 'user_test_' . uniqid() . '@example.com';

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

    public function test_get_current_user_profile(): void
    {
        $auth_data = $this->register_and_login_user();
        $client = $auth_data['client'];
        $token = $auth_data['token'];
        $email = $auth_data['email'];

        $client->request('GET', '/api/user/me', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
        ]);

        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
        $response_data = json_decode($client->getResponse()->getContent() ?: '', true);
        $this->assertArrayHasKey('id', $response_data);
        $this->assertArrayHasKey('email', $response_data);
        $this->assertArrayHasKey('two_factor_enabled', $response_data);
        $this->assertEquals($email, $response_data['email']);
        $this->assertFalse($response_data['two_factor_enabled']);
    }

    public function test_access_protected_endpoint_without_authentication(): void
    {
        $client = static::createClient();

        $client->request('GET', '/api/user/me');

        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }

    public function test_access_protected_endpoint_with_invalid_token(): void
    {
        $client = static::createClient();

        $client->request('GET', '/api/user/me', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer invalid_token_here',
        ]);

        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }

    public function test_access_protected_endpoint_with_valid_token(): void
    {
        $auth_data = $this->register_and_login_user();
        $client = $auth_data['client'];
        $token = $auth_data['token'];

        $client->request('GET', '/api/user/me', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
        ]);

        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
    }

    public function test_change_password_with_valid_credentials(): void
    {
        $auth_data = $this->register_and_login_user();
        $client = $auth_data['client'];
        $token = $auth_data['token'];
        $new_password = 'NewPassword123!';

        $client->request('POST', '/api/user/change-password', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'current_password' => self::$test_password,
            'new_password' => $new_password,
        ]) ?: '');

        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
        $response_data = json_decode($client->getResponse()->getContent() ?: '', true);
        $this->assertArrayHasKey('message', $response_data);
        $this->assertEquals('Password changed successfully', $response_data['message']);
    }

    public function test_change_password_with_invalid_current_password(): void
    {
        $auth_data = $this->register_and_login_user();
        $client = $auth_data['client'];
        $token = $auth_data['token'];

        $client->request('POST', '/api/user/change-password', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'current_password' => 'WrongPassword123!',
            'new_password' => 'NewPassword123!',
        ]) ?: '');

        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
        $response_data = json_decode($client->getResponse()->getContent() ?: '', true);
        $this->assertArrayHasKey('error', $response_data);
        $this->assertEquals('Invalid current password', $response_data['error']);
    }

    public function test_change_password_without_authentication(): void
    {
        $client = static::createClient();

        $client->request('POST', '/api/user/change-password', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'current_password' => 'OldPassword123!',
            'new_password' => 'NewPassword123!',
        ]) ?: '');

        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }
}

