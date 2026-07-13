<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\ConnectedAccount;
use App\Entity\User;
use App\Enum\IntegrationStatus;
use App\Integration\Apple\AppleCalendarProvider;
use App\Message\PushCalendarEvent;
use App\Service\ConnectedAccountCredentials;
use Doctrine\ORM\EntityManagerInterface;
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

    /**
     * Seeds a connected account directly, since connect() would hit iCloud.
     *
     * @return array{client: KernelBrowser, token: string, account_id: int}
     */
    private function seed_connected_account(?string $target = null): array
    {
        $auth_data = $this->register_and_login_user();
        $container = static::getContainer();
        $entity_manager = $container->get(EntityManagerInterface::class);
        $credentials = $container->get(ConnectedAccountCredentials::class);

        $user = $entity_manager->getRepository(User::class)->findOneBy(['email' => $auth_data['email']]);

        $account = new ConnectedAccount();
        $account->setUserOwner($user);
        $account->setProvider(AppleCalendarProvider::KEY);
        $account->setDisplayName($auth_data['email']);
        $account->setStatus(IntegrationStatus::CONNECTED);
        $account->setCreatedAt(new \DateTimeImmutable());
        $account->setTargetCalendarHref($target);
        $account->setSyncState([
            'calendar_home' => 'https://caldav.icloud.com/123/calendars/',
            'calendars' => [
                ['href' => 'https://caldav.icloud.com/123/calendars/home/', 'display_name' => 'Home', 'sync_token' => null],
                ['href' => 'https://caldav.icloud.com/123/calendars/work/', 'display_name' => 'Work', 'sync_token' => null],
            ],
        ]);
        $credentials->write($account, ['apple_id' => $auth_data['email'], 'app_password' => 'aaaa-bbbb-cccc-dddd']);

        $entity_manager->persist($account);
        $entity_manager->flush();

        return [
            'client' => $auth_data['client'],
            'token' => $auth_data['token'],
            'account_id' => (int) $account->getId(),
        ];
    }

    public function test_providers_exposes_the_discovered_calendars(): void
    {
        $seed = $this->seed_connected_account();

        $seed['client']->request('GET', '/api/integration/providers', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $seed['token'],
        ]);

        $this->assertResponseIsSuccessful();
        $providers = json_decode($seed['client']->getResponse()->getContent() ?: '', true);

        $this->assertCount(2, $providers[0]['account']['calendars']);
        $this->assertSame('Home', $providers[0]['account']['calendars'][0]['display_name']);
        $this->assertNull($providers[0]['account']['target_calendar_href']);
    }

    public function test_patch_sets_the_target_calendar(): void
    {
        $seed = $this->seed_connected_account();

        $seed['client']->request('PATCH', '/api/integration/accounts/' . $seed['account_id'], [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $seed['token'],
            'CONTENT_TYPE' => 'application/json',
        ], json_encode(['target_calendar_href' => 'https://caldav.icloud.com/123/calendars/work/']) ?: '');

        $this->assertResponseIsSuccessful();
        $body = json_decode($seed['client']->getResponse()->getContent() ?: '', true);
        $this->assertSame('https://caldav.icloud.com/123/calendars/work/', $body['target_calendar_href']);
    }

    public function test_patch_rejects_a_calendar_that_is_not_ours(): void
    {
        $seed = $this->seed_connected_account();

        $seed['client']->request('PATCH', '/api/integration/accounts/' . $seed['account_id'], [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $seed['token'],
            'CONTENT_TYPE' => 'application/json',
        ], json_encode(['target_calendar_href' => 'https://evil.example.com/calendars/steal/']) ?: '');

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function test_creating_an_event_queues_a_push_when_a_target_is_set(): void
    {
        $seed = $this->seed_connected_account('https://caldav.icloud.com/123/calendars/home/');

        $seed['client']->request('POST', '/api/event', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $seed['token'],
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'title' => 'Push me',
            'startsAt' => (new \DateTimeImmutable('+1 day'))->setTime(10, 0)->format(\DateTimeInterface::ATOM),
            'endsAt' => (new \DateTimeImmutable('+1 day'))->setTime(11, 0)->format(\DateTimeInterface::ATOM),
            'allDay' => false,
        ]) ?: '');

        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);

        $sent = static::getContainer()->get('messenger.transport.async')->getSent();
        $pushes = array_filter($sent, static fn ($envelope) => $envelope->getMessage() instanceof PushCalendarEvent);

        $this->assertCount(1, $pushes, 'Creating an event with a target calendar must queue exactly one push.');
    }

    public function test_creating_an_event_queues_nothing_without_a_target(): void
    {
        // No target calendar chosen: the event stays local rather than being
        // written to a calendar we had to guess.
        $seed = $this->seed_connected_account();

        $seed['client']->request('POST', '/api/event', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $seed['token'],
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'title' => 'Local only',
            'startsAt' => (new \DateTimeImmutable('+1 day'))->setTime(10, 0)->format(\DateTimeInterface::ATOM),
            'endsAt' => (new \DateTimeImmutable('+1 day'))->setTime(11, 0)->format(\DateTimeInterface::ATOM),
            'allDay' => false,
        ]) ?: '');

        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);

        $sent = static::getContainer()->get('messenger.transport.async')->getSent();
        $pushes = array_filter($sent, static fn ($envelope) => $envelope->getMessage() instanceof PushCalendarEvent);

        $this->assertCount(0, $pushes);
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
