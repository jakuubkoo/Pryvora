<?php

declare(strict_types=1);

namespace App\Tests\Integration\Google;

use App\Entity\ConnectedAccount;
use App\Entity\User;
use App\Enum\EmailCategory;
use App\Integration\Google\GmailMessageMapper;
use App\Service\EncryptionService;
use App\Triage\TriageClassifier;
use PHPUnit\Framework\TestCase;

class GmailMessageMapperTest extends TestCase
{
    private GmailMessageMapper $mapper;
    private EncryptionService $encryption_service;
    private TriageClassifier $classifier;
    private ConnectedAccount $account;

    protected function setUp(): void
    {
        $this->encryption_service = new EncryptionService(base64_encode(random_bytes(32)));
        $this->mapper = new GmailMessageMapper($this->encryption_service);
        $this->classifier = new TriageClassifier();

        $this->account = new ConnectedAccount();
        $this->account->setUserOwner(new User());
        $this->account->setProvider('gmail');
        $this->account->setExternalAccountId('me@example.com');
    }

    public function testParsesANamedSenderAndLowercasesTheAddress(): void
    {
        $signals = $this->mapper->to_signals($this->payload(from: '"Jana Novak" <Jana@Example.COM>'), [], 'me@example.com');

        $this->assertSame('jana@example.com', $signals->from_email);
        $this->assertSame('Jana Novak', $signals->from_name);
    }

    public function testParsesABareSenderAddress(): void
    {
        $signals = $this->mapper->to_signals($this->payload(from: 'jana@example.com'), [], 'me@example.com');

        $this->assertSame('jana@example.com', $signals->from_email);
        $this->assertNull($signals->from_name);
    }

    public function testHeaderNamesAreLowercasedSoLookupsAreCaseInsensitive(): void
    {
        $signals = $this->mapper->to_signals($this->payload(extra_headers: [
            ['name' => 'List-Unsubscribe', 'value' => '<https://x.example/u/1>'],
        ]), [], 'me@example.com');

        $this->assertTrue($signals->has_header('list-unsubscribe'));
    }

    /**
     * A user's own Gmail labels are their content ("Divorce lawyer"). labelIds is a
     * plaintext column, so anything not a Gmail system label must be dropped here.
     */
    public function testUserCreatedLabelsAreStrippedAndSystemLabelsKept(): void
    {
        $message = $this->map($this->payload(labels: ['INBOX', 'UNREAD', 'Label_8', 'Divorce lawyer', 'CATEGORY_UPDATES']));

        $this->assertSame(['INBOX', 'UNREAD', 'CATEGORY_UPDATES'], $message->getLabelIds());
    }

    public function testContentFieldsAreCiphertextAndRoundTrip(): void
    {
        $message = $this->map($this->payload(subject: 'Quarterly figures'));

        $this->assertNotSame('Quarterly figures', $message->getSubject());
        $this->assertSame('Quarterly figures', $this->encryption_service->decrypt((string) $message->getSubject()));
    }

    /**
     * An unsubscribe link usually carries a per-user token, so it is a secret and
     * must not sit in the clear next to the sender.
     */
    public function testUnsubscribeUrlIsExtractedFromTheHeaderAndEncrypted(): void
    {
        $message = $this->map($this->payload(extra_headers: [
            ['name' => 'List-Unsubscribe', 'value' => '<mailto:u@x.example>, <https://x.example/u/secret-token>'],
        ]));

        $this->assertTrue($message->hasListUnsubscribe());
        $this->assertSame(
            'https://x.example/u/secret-token',
            $this->encryption_service->decrypt((string) $message->getUnsubscribeUrl()),
        );
    }

    public function testFromEmailStaysPlaintextForQuerying(): void
    {
        $message = $this->map($this->payload(from: 'Deals <deals@shop.example>'));

        $this->assertSame('deals@shop.example', $message->getFromEmail());
    }

    public function testInternalDateMillisecondsBecomeReceivedAt(): void
    {
        // 2026-07-13T09:00:00Z in milliseconds.
        $message = $this->map($this->payload(internal_date: '1783933200000'));

        $this->assertSame('2026-07-13 09:00:00', $message->getReceivedAt()->format('Y-m-d H:i:s'));
    }

    public function testUnreadAndStarredMirrorGmailLabels(): void
    {
        $message = $this->map($this->payload(labels: ['INBOX', 'UNREAD', 'STARRED']));

        $this->assertTrue($message->isUnread());
        $this->assertTrue($message->isStarred());
    }

    public function testSnippetIsHtmlDecodedBeforeEncryption(): void
    {
        $message = $this->map($this->payload(snippet: 'Tom &amp; Jerry said &quot;hi&quot;'));

        $this->assertSame('Tom & Jerry said "hi"', $this->encryption_service->decrypt((string) $message->getSnippet()));
    }

    /**
     * The end-to-end shape: a Gmail payload in, a classified row out.
     */
    public function testClassificationIsPersistedWithItsReason(): void
    {
        $message = $this->map($this->payload(
            from: 'newsletter@substack.example',
            labels: ['INBOX', 'CATEGORY_PROMOTIONS'],
            extra_headers: [['name' => 'List-Unsubscribe', 'value' => '<https://s.example/u/1>']],
        ));

        $reason = $message->getCategoryReason() ?? [];
        $terminal = array_values(array_filter($reason, static fn (array $entry): bool => $entry['terminal']));

        $this->assertSame(EmailCategory::PROMOTION, $message->getCategory());
        $this->assertSame('promotions_bulk', $terminal[0]['rule']);

        // The reason is policy ids only — never matched content. This is what lets
        // the column stay plaintext, so assert it rather than trusting it.
        foreach ($reason as $entry) {
            $this->assertMatchesRegularExpression('/^[a-z_]+$/', $entry['rule']);
        }
    }

    /**
     * A sender the user has replied to survives every noise signal on the message.
     */
    public function testKnownCorrespondentIsPriorityEvenWithANewsletterHeader(): void
    {
        $payload = $this->payload(
            from: 'founder@startup.example',
            labels: ['INBOX', 'CATEGORY_PROMOTIONS'],
            extra_headers: [['name' => 'List-Unsubscribe', 'value' => '<https://s.example/u/1>']],
        );

        $signals = $this->mapper->to_signals($payload, ['founder@startup.example'], 'me@example.com');

        $this->assertSame(EmailCategory::PRIORITY, $this->classifier->classify($signals)->category);
    }

    /**
     * @param list<array{name: string, value: string}> $extra_headers
     * @param list<string>                             $labels
     *
     * @return array<string, mixed>
     */
    private function payload(
        string $from = 'jana@example.com',
        string $subject = 'Subject',
        string $snippet = 'A snippet',
        string $internal_date = '1783933200000',
        array $labels = ['INBOX'],
        array $extra_headers = [],
    ): array {
        return [
            'id' => 'msg-1',
            'threadId' => 'thread-1',
            'snippet' => $snippet,
            'internalDate' => $internal_date,
            'labelIds' => $labels,
            'payload' => [
                'headers' => array_merge([
                    ['name' => 'From', 'value' => $from],
                    ['name' => 'To', 'value' => 'me@example.com'],
                    ['name' => 'Subject', 'value' => $subject],
                ], $extra_headers),
            ],
        ];
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function map(array $payload): \App\Entity\EmailMessage
    {
        $signals = $this->mapper->to_signals($payload, [], 'me@example.com');
        $result = $this->classifier->classify($signals);
        $message = $this->mapper->to_email_message($payload, $this->account, $result, null);

        $this->assertNotNull($message);

        return $message;
    }
}
