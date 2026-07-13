<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Integration\Apple\AppleCalendarProvider;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

class IntegrationControllerTest extends WebTestCase
{
    private static string $test_password = 'TestPassword123!';

    private function register_and_login_user(?KernelBrowser $client = null): array
    {
        $client ??= static::createClient();
        $email = 'integration_test_' . uniqid() . '@example.com';

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

        $response_data = json_decode($client->getResponse()->getContent() ?: '', true);

        return ['client' => $client, 'token' => $response_data['token'] ?? '', 'email' => $email];
    }

    public function test_providers_requires_authentication(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/integration/providers');

        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }

    public function test_providers_lists_apple_calendar_with_its_connect_form(): void
    {
        $auth_data = $this->register_and_login_user();

        $auth_data['client']->request('GET', '/api/integration/providers', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $auth_data['token'],
        ]);

        $this->assertResponseIsSuccessful();
        $providers = json_decode($auth_data['client']->getResponse()->getContent() ?: '', true);

        $apple = null;

        foreach ($providers as $provider) {
            if (AppleCalendarProvider::KEY === $provider['key']) {
                $apple = $provider;
            }
        }

        $this->assertNotNull($apple, 'Apple Calendar provider should be registered.');
        $this->assertNull($apple['account']);

        // The connect dialog is rendered from this, so the field contract matters.
        $field_names = array_column($apple['form'], 'name');
        $this->assertSame(['apple_id', 'app_password'], $field_names);
        $this->assertSame('password', $apple['form'][1]['type']);
        $this->assertArrayHasKey('help', $apple['form'][1]);
    }

    public function test_accounts_is_empty_for_a_new_user(): void
    {
        $auth_data = $this->register_and_login_user();

        $auth_data['client']->request('GET', '/api/integration/accounts', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $auth_data['token'],
        ]);

        $this->assertResponseIsSuccessful();
        $this->assertSame([], json_decode($auth_data['client']->getResponse()->getContent() ?: '', true));
    }

    public function test_connect_rejects_an_unknown_provider(): void
    {
        $auth_data = $this->register_and_login_user();

        $auth_data['client']->request('POST', '/api/integration/accounts', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $auth_data['token'],
            'CONTENT_TYPE' => 'application/json',
        ], json_encode(['provider' => 'myspace', 'credentials' => []]) ?: '');

        $this->assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
    }

    public function test_connect_rejects_missing_credentials_at_the_form(): void
    {
        $auth_data = $this->register_and_login_user();

        $auth_data['client']->request('POST', '/api/integration/accounts', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $auth_data['token'],
            'CONTENT_TYPE' => 'application/json',
        ], json_encode(['provider' => AppleCalendarProvider::KEY, 'credentials' => ['apple_id' => '']]) ?: '');

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $body = json_decode($auth_data['client']->getResponse()->getContent() ?: '', true);
        $this->assertArrayHasKey('error', $body);
    }

    public function test_another_users_account_is_not_reachable(): void
    {
        $auth_data = $this->register_and_login_user();

        // Nothing is connected, so any id is somebody else's or nonexistent;
        // either way it must not leak.
        $auth_data['client']->request('POST', '/api/integration/accounts/999999/sync', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $auth_data['token'],
        ]);

        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    public function test_sync_and_disconnect_require_authentication(): void
    {
        $client = static::createClient();

        $client->request('POST', '/api/integration/accounts/1/sync');
        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);

        $client->request('DELETE', '/api/integration/accounts/1');
        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }
}
