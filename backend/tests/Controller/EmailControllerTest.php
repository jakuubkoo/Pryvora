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

        // The sub-chips under Noise still filter to one exact category.
        $this->assertCount(2, $items);
        $this->assertSame(['newsletter', 'newsletter'], array_column($items, 'category'));
    }

    public function test_unknown_category_is_rejected(): void
    {
        $seed = $this->seed_inbox();

        $seed['client']->request('GET', '/api/email?category=nonsense', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer '.$seed['token'],
        ]);

        $this->assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
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

        // Dismissing hides one message, not the sender — the other newsletter from
        // the same address is still there. That difference is exactly why blocking
        // a sender had to exist as well.
        $this->assertCount(1, json_decode($seed['client']->getResponse()->getContent() ?: '', true)['items']);

        // Still there, just hidden — dismissing is a local view state, not a delete.
        $seed['client']->request('GET', '/api/email?category=newsletter&include_dismissed=1', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer '.$seed['token'],
        ]);

        $this->assertCount(2, json_decode($seed['client']->getResponse()->getContent() ?: '', true)['items']);
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
            // A second email from the same newsletter. Blocking the sender must sweep
            // both away, not just the one that was clicked.
            ['digest@substack.example', 'Last week', EmailCategory::NEWSLETTER, 'list_id_header', '-9 days'],
            ['deals@shop.example', 'HUGE SALE', EmailCategory::PROMOTION, 'gmail_promotions', '-3 hours'],
            ['no-reply@github.example', 'Build passed', EmailCategory::NOTIFICATION, 'noreply_sender', '-4 hours'],
            ['phisher@evil.example', 'Your account', EmailCategory::SPAM, 'gmail_spam', '-5 hours'],
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
            // No rules seeded, so the effective and auto categories start identical —
            // exactly the state the migration leaves real rows in.
            $message->setAutoCategory($category);
            $message->setCategoryReason([['rule' => $rule, 'weight' => 10, 'terminal' => true]]);
            $message->setScore(10);
            $message->setLabelIds(['INBOX']);
            $message->setCreatedAt(new \DateTimeImmutable());

            $entity_manager->persist($message);
            $entity_manager->flush();

            if (EmailCategory::NEWSLETTER === $category && 0 === $newsletter_id) {
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

    /**
     * @return array<string, mixed>
     */
    private function get_json(KernelBrowser $client): array
    {
        return json_decode($client->getResponse()->getContent() ?: '', true) ?? [];
    }

    private function list_categories(KernelBrowser $client, string $token, string $tab): array
    {
        $client->request('GET', '/api/email?category='.$tab, [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ]);

        return array_column($this->get_json($client)['items'] ?? [], 'category');
    }

    private function put_rule(KernelBrowser $client, string $token, string $sender, string $verdict): void
    {
        $client->request('PUT', '/api/email/rules', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
            'CONTENT_TYPE' => 'application/json',
        ], json_encode(['sender_email' => $sender, 'verdict' => $verdict]) ?: '');
    }

    /**
     * The bug this whole change exists to fix: Noise used to mean "not priority",
     * so a blocked sender and Gmail's spam sat in the same drawer as a newsletter.
     */
    public function test_noise_excludes_spam_and_blocked(): void
    {
        $seed = $this->seed_inbox();
        $categories = $this->list_categories($seed['client'], $seed['token'], 'noise');

        $this->assertContains('newsletter', $categories);
        $this->assertContains('promotion', $categories);
        $this->assertContains('notification', $categories);

        $this->assertNotContains('spam', $categories);
        $this->assertNotContains('blocked', $categories);
        $this->assertNotContains('priority', $categories);
    }

    public function test_blocked_tab_holds_spam_and_blocked_senders(): void
    {
        $seed = $this->seed_inbox();

        $this->assertSame(['spam'], $this->list_categories($seed['client'], $seed['token'], 'blocked'));

        $this->put_rule($seed['client'], $seed['token'], 'deals@shop.example', 'blocked');

        // Order is newest-first, not category-first, so compare as a set.
        $this->assertEqualsCanonicalizing(
            ['spam', 'blocked'],
            $this->list_categories($seed['client'], $seed['token'], 'blocked'),
            'A blocked sender joins Gmail spam in the Blocked tab.',
        );
    }

    /**
     * Blocking has to sweep the mail already in the database. Blocking a sender and
     * still seeing forty of their old emails would read as the button not working.
     */
    public function test_blocking_a_sender_retroactively_moves_all_their_mail(): void
    {
        $seed = $this->seed_inbox();

        $this->put_rule($seed['client'], $seed['token'], 'digest@substack.example', 'blocked');
        $this->assertResponseIsSuccessful();

        // Both of that newsletter's emails, not just the one that was clicked.
        $this->assertSame(2, $this->get_json($seed['client'])['messages_moved']);

        $this->assertNotContains('newsletter', $this->list_categories($seed['client'], $seed['token'], 'noise'));
        $this->assertCount(2, array_filter(
            $this->list_categories($seed['client'], $seed['token'], 'blocked'),
            static fn (string $c): bool => 'blocked' === $c,
        ));
    }

    /**
     * Un-blocking must restore the *original* verdict. Dumping a newsletter into
     * Priority because it was once un-blocked would be worse than not offering the
     * button at all.
     */
    public function test_unblocking_restores_the_original_category_not_priority(): void
    {
        $seed = $this->seed_inbox();

        $this->put_rule($seed['client'], $seed['token'], 'digest@substack.example', 'blocked');

        $seed['client']->request('GET', '/api/email/rules', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer '.$seed['token'],
        ]);

        $rule_id = $this->get_json($seed['client'])[0]['id'];

        $seed['client']->request('DELETE', '/api/email/rules/'.$rule_id, [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer '.$seed['token'],
        ]);

        $this->assertResponseIsSuccessful();
        $this->assertSame(2, $this->get_json($seed['client'])['messages_restored']);

        $noise = $this->list_categories($seed['client'], $seed['token'], 'noise');
        $needs_you = $this->list_categories($seed['client'], $seed['token'], 'needs_you');

        $this->assertSame(2, \count(array_filter($noise, static fn (string $c): bool => 'newsletter' === $c)));
        $this->assertSame(['priority'], $needs_you, 'Un-blocking must not promote a newsletter to Priority.');
    }

    public function test_always_show_lifts_a_notification_into_needs_you(): void
    {
        $seed = $this->seed_inbox();

        $this->put_rule($seed['client'], $seed['token'], 'no-reply@github.example', 'always_show');
        $this->assertResponseIsSuccessful();
        $this->assertSame(1, $this->get_json($seed['client'])['messages_moved']);

        $this->assertContains('priority', $this->list_categories($seed['client'], $seed['token'], 'needs_you'));
        $this->assertNotContains('notification', $this->list_categories($seed['client'], $seed['token'], 'noise'));
    }

    /**
     * A From: header is trivially forged, so "always show" must not be a way to
     * walk spam into the priority inbox.
     */
    public function test_always_show_cannot_rescue_a_sender_gmail_called_spam(): void
    {
        $seed = $this->seed_inbox();

        $this->put_rule($seed['client'], $seed['token'], 'phisher@evil.example', 'always_show');
        $this->assertResponseIsSuccessful();

        $this->assertSame(0, $this->get_json($seed['client'])['messages_moved']);
        $this->assertSame(['spam'], $this->list_categories($seed['client'], $seed['token'], 'blocked'));
        $this->assertNotContains('spam', $this->list_categories($seed['client'], $seed['token'], 'needs_you'));
    }

    public function test_stats_split_across_the_three_tabs(): void
    {
        $seed = $this->seed_inbox();

        $seed['client']->request('GET', '/api/email/stats', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer '.$seed['token'],
        ]);

        $body = $this->get_json($seed['client']);

        $this->assertSame(1, $body['needs_you']);
        $this->assertSame(4, $body['noise'], '2 newsletters + 1 promotion + 1 notification.');
        $this->assertSame(1, $body['blocked'], 'The Gmail-flagged spam.');
    }

    public function test_a_rule_is_rejected_without_a_verdict(): void
    {
        $seed = $this->seed_inbox();

        $seed['client']->request('PUT', '/api/email/rules', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer '.$seed['token'],
            'CONTENT_TYPE' => 'application/json',
        ], json_encode(['sender_email' => 'x@example.com', 'verdict' => 'nonsense']) ?: '');

        $this->assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
    }

    public function test_another_users_rule_is_not_reachable(): void
    {
        $victim = $this->seed_inbox();

        $this->put_rule($victim['client'], $victim['token'], 'digest@substack.example', 'blocked');

        $victim['client']->request('GET', '/api/email/rules', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer '.$victim['token'],
        ]);

        $rule_id = $this->get_json($victim['client'])[0]['id'];

        $attacker = $this->register_and_login_user($victim['client']);

        $attacker['client']->request('DELETE', '/api/email/rules/'.$rule_id, [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer '.$attacker['token'],
        ]);

        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }
}
