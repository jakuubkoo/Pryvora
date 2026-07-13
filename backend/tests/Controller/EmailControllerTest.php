<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\ConnectedAccount;
use App\Entity\EmailMessage;
use App\Entity\User;
use App\Enum\EmailCategory;
use App\Enum\IntegrationStatus;
use App\Integration\Google\GmailProvider;
use App\Service\ConnectedAccountCredentials;
use App\Service\EncryptionService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

class EmailControllerTest extends WebTestCase
{
    private static string $test_password = 'TestPassword123!';

    public function test_list_requires_authentication(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/email');

        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }

    public function test_list_decrypts_content_and_returns_the_sender_in_the_clear(): void
    {
        $seed = $this->seed_inbox();

        $seed['client']->request('GET', '/api/email', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer '.$seed['token'],
        ]);

        $this->assertResponseIsSuccessful();
        $body = json_decode($seed['client']->getResponse()->getContent() ?: '', true);

        $subjects = array_column($body['items'], 'subject');

        $this->assertContains('Are you free Thursday?', $subjects, 'Subjects must come back decrypted.');
        $this->assertContains('deals@shop.example', array_column($body['items'], 'from_email'));
    }

    public function test_newest_mail_comes_first(): void
    {
        $seed = $this->seed_inbox();

        $seed['client']->request('GET', '/api/email', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer '.$seed['token'],
        ]);

        $received = array_column(json_decode($seed['client']->getResponse()->getContent() ?: '', true)['items'], 'received_at');
        $sorted = $received;
        rsort($sorted);

        $this->assertSame($sorted, $received);
    }

    public function test_category_filter_returns_only_that_category(): void
    {
        $seed = $this->seed_inbox();

        $seed['client']->request('GET', '/api/email?category=newsletter', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer '.$seed['token'],
        ]);

        $this->assertResponseIsSuccessful();
        $items = json_decode($seed['client']->getResponse()->getContent() ?: '', true)['items'];

        $this->assertCount(1, $items);
        $this->assertSame('newsletter', $items[0]['category']);
    }

    /**
     * 'noise' is the Inbox's second tab: everything that is not PRIORITY.
     */
    public function test_noise_filter_returns_everything_except_priority(): void
    {
        $seed = $this->seed_inbox();

        $seed['client']->request('GET', '/api/email?category=noise', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer '.$seed['token'],
        ]);

        $this->assertResponseIsSuccessful();
        $categories = array_column(json_decode($seed['client']->getResponse()->getContent() ?: '', true)['items'], 'category');

        $this->assertNotContains('priority', $categories);
        $this->assertContains('newsletter', $categories);
        $this->assertContains('promotion', $categories);
    }

    public function test_unknown_category_is_rejected(): void
    {
        $seed = $this->seed_inbox();

        $seed['client']->request('GET', '/api/email?category=nonsense', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer '.$seed['token'],
        ]);

        $this->assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
    }

    public function test_stats_split_needs_you_from_noise(): void
    {
        $seed = $this->seed_inbox();

        $seed['client']->request('GET', '/api/email/stats', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer '.$seed['token'],
        ]);

        $this->assertResponseIsSuccessful();
        $body = json_decode($seed['client']->getResponse()->getContent() ?: '', true);

        $this->assertSame(1, $body['needs_you']);
        $this->assertSame(3, $body['noise']);
        $this->assertSame(1, $body['by_category']['newsletter']);
    }

    public function test_dismissing_hides_a_message_without_deleting_it(): void
    {
        $seed = $this->seed_inbox();

        $seed['client']->request('POST', '/api/email/'.$seed['newsletter_id'].'/dismiss', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer '.$seed['token'],
        ]);

        $this->assertResponseIsSuccessful();
        $this->assertNotNull(json_decode($seed['client']->getResponse()->getContent() ?: '', true)['dismissed_at']);

        $seed['client']->request('GET', '/api/email?category=newsletter', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer '.$seed['token'],
        ]);

        $this->assertCount(0, json_decode($seed['client']->getResponse()->getContent() ?: '', true)['items']);

        // Still there, just hidden — dismissing is a local view state, not a delete.
        $seed['client']->request('GET', '/api/email?category=newsletter&include_dismissed=1', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer '.$seed['token'],
        ]);

        $this->assertCount(1, json_decode($seed['client']->getResponse()->getContent() ?: '', true)['items']);
    }

    public function test_dismissing_can_be_undone(): void
    {
        $seed = $this->seed_inbox();

        $seed['client']->request('POST', '/api/email/'.$seed['newsletter_id'].'/dismiss', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer '.$seed['token'],
        ]);

        $seed['client']->request('DELETE', '/api/email/'.$seed['newsletter_id'].'/dismiss', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer '.$seed['token'],
        ]);

        $this->assertResponseIsSuccessful();
        $this->assertNull(json_decode($seed['client']->getResponse()->getContent() ?: '', true)['dismissed_at']);
    }

    /**
     * The one that matters: another user's mail must be invisible, not merely
     * unlisted.
     */
    public function test_another_users_mail_is_not_reachable(): void
    {
        $victim = $this->seed_inbox();

        // Same client: Symfony boots the kernel exactly once per test.
        $attacker = $this->register_and_login_user($victim['client']);

        $attacker['client']->request('GET', '/api/email', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer '.$attacker['token'],
        ]);

        $this->assertResponseIsSuccessful();
        $this->assertCount(0, json_decode($attacker['client']->getResponse()->getContent() ?: '', true)['items']);

        $attacker['client']->request('POST', '/api/email/'.$victim['newsletter_id'].'/dismiss', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer '.$attacker['token'],
        ]);

        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    /**
     * @return array{client: KernelBrowser, token: string, email: string}
     */
    private function register_and_login_user(?KernelBrowser $client = null): array
    {
        $client ??= static::createClient();
        $email = 'email_test_'.uniqid().'@example.com';

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

    /**
     * Seeds a Gmail account and four classified messages. Nothing here calls Google.
     *
     * @return array{client: KernelBrowser, token: string, newsletter_id: int}
     */
    private function seed_inbox(): array
    {
        $auth_data = $this->register_and_login_user();
        $container = static::getContainer();
        $entity_manager = $container->get(EntityManagerInterface::class);
        $credentials = $container->get(ConnectedAccountCredentials::class);
        $encryption = $container->get(EncryptionService::class);

        $user = $entity_manager->getRepository(User::class)->findOneBy(['email' => $auth_data['email']]);

        $account = new ConnectedAccount();
        $account->setUserOwner($user);
        $account->setProvider(GmailProvider::KEY);
        $account->setDisplayName($auth_data['email']);
        $account->setExternalAccountId($auth_data['email']);
        $account->setStatus(IntegrationStatus::CONNECTED);
        $account->setCreatedAt(new \DateTimeImmutable());
        $account->setSyncState([
            'history_id' => '12345',
            'backfill_complete' => true,
            'correspondents' => [],
        ]);
        $credentials->write($account, ['access_token' => 'fake', 'refresh_token' => 'fake']);

        $entity_manager->persist($account);

        $rows = [
            ['jana@example.com', 'Are you free Thursday?', EmailCategory::PRIORITY, 'default_priority', '-1 hour'],
            ['digest@substack.example', 'Your weekly digest', EmailCategory::NEWSLETTER, 'list_id_header', '-2 hours'],
            ['deals@shop.example', 'HUGE SALE', EmailCategory::PROMOTION, 'gmail_promotions', '-3 hours'],
            ['no-reply@github.example', 'Build passed', EmailCategory::NOTIFICATION, 'noreply_sender', '-4 hours'],
        ];

        $newsletter_id = 0;

        foreach ($rows as $index => [$from, $subject, $category, $rule, $ago]) {
            $message = new EmailMessage();
            $message->setUserOwner($user);
            $message->setConnectedAccount($account);
            $message->setGmailMessageId('msg-'.$index);
            $message->setGmailThreadId('thread-'.$index);
            $message->setSubject($encryption->encrypt($subject));
            $message->setSnippet($encryption->encrypt('A snippet'));
            $message->setFromName($encryption->encrypt('Sender'));
            $message->setFromEmail($from);
            $message->setReceivedAt(new \DateTimeImmutable($ago));
            $message->setCategory($category);
            $message->setCategoryReason([['rule' => $rule, 'weight' => 10, 'terminal' => true]]);
            $message->setScore(10);
            $message->setLabelIds(['INBOX']);
            $message->setCreatedAt(new \DateTimeImmutable());

            $entity_manager->persist($message);
            $entity_manager->flush();

            if (EmailCategory::NEWSLETTER === $category) {
                $newsletter_id = (int) $message->getId();
            }
        }

        $entity_manager->flush();

        return [
            'client' => $auth_data['client'],
            'token' => $auth_data['token'],
            'newsletter_id' => $newsletter_id,
        ];
    }
}
