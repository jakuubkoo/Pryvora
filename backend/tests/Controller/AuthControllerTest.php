<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

class AuthControllerTest extends WebTestCase
{
    private static string $test_email = '';
    private static string $test_password = 'TestPassword123!';

    protected function setUp(): void
    {
        parent::setUp();
        self::$test_email = 'test_' . uniqid() . '@example.com';
    }

    public function test_register_with_valid_data(): void
    {
        $client = static::createClient();

        $client->request('POST', '/api/auth/register', [], [], ['CONTENT_TYPE' => 'application/json'], json_encode([
            'firstName' => 'John',
            'lastName' => 'Doe',
            'email' => self::$test_email,
            'password' => self::$test_password,
            'confirmPassword' => self::$test_password,
        ]) ?: '');

        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $response_data = json_decode($client->getResponse()->getContent() ?: '', true);
        $this->assertArrayHasKey('message', $response_data);
        $this->assertEquals('User registered successfully', $response_data['message']);
    }

    public function test_register_with_duplicate_email(): void
    {
        $client = static::createClient();
        $email = 'duplicate_' . uniqid() . '@example.com';

        // First registration
        $client->request('POST', '/api/auth/register', [], [], ['CONTENT_TYPE' => 'application/json'], json_encode([
            'firstName' => 'John',
            'lastName' => 'Doe',
            'email' => $email,
            'password' => self::$test_password,
            'confirmPassword' => self::$test_password,
        ]) ?: '');

        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);

        // Second registration with same email
        $client->request('POST', '/api/auth/register', [], [], ['CONTENT_TYPE' => 'application/json'], json_encode([
            'firstName' => 'Jane',
            'lastName' => 'Smith',
            'email' => $email,
            'password' => self::$test_password,
            'confirmPassword' => self::$test_password,
        ]) ?: '');

        $this->assertResponseStatusCodeSame(Response::HTTP_CONFLICT);
        $response_data = json_decode($client->getResponse()->getContent() ?: '', true);
        $this->assertArrayHasKey('error', $response_data);
        $this->assertEquals('Email already exists', $response_data['error']);
    }

    public function test_register_with_invalid_data(): void
    {
        $client = static::createClient();

        $client->request('POST', '/api/auth/register', [], [], ['CONTENT_TYPE' => 'application/json'], json_encode([
            'firstName' => 'J',
            'lastName' => 'D',
            'email' => 'invalid-email',
            'password' => 'short',
            'confirmPassword' => 'different',
        ]) ?: '');

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $response_data = json_decode($client->getResponse()->getContent() ?: '', true);
        $this->assertArrayHasKey('errors', $response_data);
    }

    public function test_register_with_password_mismatch(): void
    {
        $client = static::createClient();

        $client->request('POST', '/api/auth/register', [], [], ['CONTENT_TYPE' => 'application/json'], json_encode([
            'firstName' => 'John',
            'lastName' => 'Doe',
            'email' => 'test_' . uniqid() . '@example.com',
            'password' => self::$test_password,
            'confirmPassword' => 'DifferentPassword123!',
        ]) ?: '');

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $response_data = json_decode($client->getResponse()->getContent() ?: '', true);
        $this->assertArrayHasKey('errors', $response_data);
        $this->assertArrayHasKey('confirmPassword', $response_data['errors']);
    }

    public function test_login_with_valid_credentials(): void
    {
        $client = static::createClient();
        $email = 'login_test_' . uniqid() . '@example.com';

        // Register user first
        $client->request('POST', '/api/auth/register', [], [], ['CONTENT_TYPE' => 'application/json'], json_encode([
            'firstName' => 'John',
            'lastName' => 'Doe',
            'email' => $email,
            'password' => self::$test_password,
            'confirmPassword' => self::$test_password,
        ]) ?: '');

        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);

        // Login
        $client->request('POST', '/api/auth/login', [], [], ['CONTENT_TYPE' => 'application/json'], json_encode([
            'email' => $email,
            'password' => self::$test_password,
        ]) ?: '');

        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
        $response_data = json_decode($client->getResponse()->getContent() ?: '', true);
        $this->assertArrayHasKey('token', $response_data);
        $this->assertArrayHasKey('message', $response_data);
        $this->assertEquals('Login successful', $response_data['message']);
    }

    public function test_login_with_invalid_credentials(): void
    {
        $client = static::createClient();

        $client->request('POST', '/api/auth/login', [], [], ['CONTENT_TYPE' => 'application/json'], json_encode([
            'email' => 'nonexistent@example.com',
            'password' => 'WrongPassword123!',
        ]) ?: '');

        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
        $response_data = json_decode($client->getResponse()->getContent() ?: '', true);
        $this->assertArrayHasKey('error', $response_data);
        $this->assertEquals('Invalid credentials', $response_data['error']);
    }

    public function test_login_with_missing_fields(): void
    {
        $client = static::createClient();

        $client->request('POST', '/api/auth/login', [], [], ['CONTENT_TYPE' => 'application/json'], json_encode([
            'email' => 'test@example.com',
        ]) ?: '');

        $this->assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
        $response_data = json_decode($client->getResponse()->getContent() ?: '', true);
        $this->assertArrayHasKey('error', $response_data);
    }

    public function test_logout_functionality(): void
    {
        $client = static::createClient();
        $email = 'logout_test_' . uniqid() . '@example.com';

        // Register and login
        $client->request('POST', '/api/auth/register', [], [], ['CONTENT_TYPE' => 'application/json'], json_encode([
            'firstName' => 'John',
            'lastName' => 'Doe',
            'email' => $email,
            'password' => self::$test_password,
            'confirmPassword' => self::$test_password,
        ]) ?: '');

        $client->request('POST', '/api/auth/login', [], [], ['CONTENT_TYPE' => 'application/json'], json_encode([
            'email' => $email,
            'password' => self::$test_password,
        ]) ?: '');

        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
        $login_data = json_decode($client->getResponse()->getContent() ?: '', true);
        $token = $login_data['token'] ?? '';

        // Logout with token
        $client->request('POST', '/api/auth/logout', [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
        ]);

        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
        $response_data = json_decode($client->getResponse()->getContent() ?: '', true);
        $this->assertArrayHasKey('message', $response_data);
        $this->assertEquals('Logout successful', $response_data['message']);
    }

    public function test_refresh_token_with_valid_token(): void
    {
        $client = static::createClient();
        $email = 'refresh_test_' . uniqid() . '@example.com';

        // Register and login
        $client->request('POST', '/api/auth/register', [], [], ['CONTENT_TYPE' => 'application/json'], json_encode([
            'firstName' => 'John',
            'lastName' => 'Doe',
            'email' => $email,
            'password' => self::$test_password,
            'confirmPassword' => self::$test_password,
        ]) ?: '');

        $client->request('POST', '/api/auth/login', [], [], ['CONTENT_TYPE' => 'application/json'], json_encode([
            'email' => $email,
            'password' => self::$test_password,
        ]) ?: '');

        $this->assertResponseStatusCodeSame(Response::HTTP_OK);

        // Refresh token
        $client->request('POST', '/api/auth/refresh');

        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
        $response_data = json_decode($client->getResponse()->getContent() ?: '', true);
        $this->assertArrayHasKey('token', $response_data);
        $this->assertArrayHasKey('message', $response_data);
        $this->assertEquals('Token refreshed', $response_data['message']);
    }

    public function test_refresh_token_without_cookie(): void
    {
        $client = static::createClient();

        $client->request('POST', '/api/auth/refresh');

        $this->assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
        $response_data = json_decode($client->getResponse()->getContent() ?: '', true);
        $this->assertArrayHasKey('error', $response_data);
        $this->assertEquals('Refresh token not provided', $response_data['error']);
    }
}
