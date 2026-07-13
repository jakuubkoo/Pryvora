<?php

declare(strict_types=1);

namespace App\Tests\Triage;

use App\Enum\EmailCategory;
use App\Triage\TriageClassifier;
use App\Triage\TriageSignals;
use PHPUnit\Framework\TestCase;

class TriageClassifierTest extends TestCase
{
    private TriageClassifier $classifier;

    protected function setUp(): void
    {
        $this->classifier = new TriageClassifier();
    }

    public function testListIdHeaderIsANewsletter(): void
    {
        $result = $this->classifier->classify($this->signals(
            from_email: 'digest@substack.com',
            headers: ['list-id' => '<some.list.substack.com>'],
        ));

        $this->assertSame(EmailCategory::NEWSLETTER, $result->category);
        $this->assertSame('list_id_header', $result->deciding_rule());
    }

    /**
     * Rule 3 must beat rule 6: a bulk mail Gmail already filed under Promotions
     * is marketing, not a newsletter, even though it carries the same header.
     */
    public function testUnsubscribeHeaderPlusPromotionsLabelIsAPromotionNotANewsletter(): void
    {
        $result = $this->classifier->classify($this->signals(
            from_email: 'deals@shop.example',
            headers: ['list-unsubscribe' => '<https://shop.example/u/abc>'],
            label_ids: ['INBOX', 'CATEGORY_PROMOTIONS'],
        ));

        $this->assertSame(EmailCategory::PROMOTION, $result->category);
        $this->assertSame('promotions_bulk', $result->deciding_rule());
    }

    /**
     * An ordering assertion, not just an outcome assertion. Both rules would
     * land on NOTIFICATION, so only the rule id proves noreply_sender (9) was
     * reached before gmail_updates (12).
     */
    public function testNoreplySenderOutranksTheGmailUpdatesLabel(): void
    {
        $result = $this->classifier->classify($this->signals(
            from_email: 'no-reply@github.com',
            label_ids: ['INBOX', 'CATEGORY_UPDATES'],
        ));

        $this->assertSame(EmailCategory::NOTIFICATION, $result->category);
        $this->assertSame('noreply_sender', $result->deciding_rule());
    }

    public function testPlusTagIsStrippedBeforeMatchingTheNoreplyPattern(): void
    {
        $result = $this->classifier->classify($this->signals(
            from_email: 'notifications+abc123@github.com',
        ));

        $this->assertSame(EmailCategory::NOTIFICATION, $result->category);
        $this->assertSame('noreply_sender', $result->deciding_rule());
    }

    /**
     * The most important assertion in the suite.
     *
     * If the user has ever replied to this sender, nothing else matters — not
     * the unsubscribe header, not Gmail's Promotions label. Their bulk mail is
     * not noise to this user.
     */
    public function testSenderTheUserHasRepliedToIsAlwaysPriority(): void
    {
        $result = $this->classifier->classify($this->signals(
            from_email: 'founder@somestartup.example',
            headers: ['list-unsubscribe' => '<https://somestartup.example/u/xyz>'],
            label_ids: ['INBOX', 'CATEGORY_PROMOTIONS'],
            user_has_replied_to_sender: true,
        ));

        $this->assertSame(EmailCategory::PRIORITY, $result->category);
        $this->assertSame('user_replied_to_sender', $result->deciding_rule());
    }

    /**
     * Spam is the one thing that outranks even sender reputation — a spoofed
     * From: is exactly how a phisher would exploit the reputation rule.
     */
    public function testGmailSpamLabelOutranksSenderReputation(): void
    {
        $result = $this->classifier->classify($this->signals(
            from_email: 'founder@somestartup.example',
            label_ids: ['SPAM'],
            user_has_replied_to_sender: true,
        ));

        $this->assertSame(EmailCategory::SPAM, $result->category);
        $this->assertSame('gmail_spam', $result->deciding_rule());
    }

    public function testPlainHumanMailFallsThroughToPriority(): void
    {
        $result = $this->classifier->classify($this->signals(
            from_email: 'jana@example.com',
            subject: 'Are you free on Thursday?',
        ));

        $this->assertSame(EmailCategory::PRIORITY, $result->category);
        $this->assertSame('default_priority', $result->deciding_rule());
        $this->assertSame(0, $result->score);
    }

    /**
     * Modifiers reorder within a bucket; they must never hijack the category.
     */
    public function testStarringANewsletterLowersItsScoreButKeepsItANewsletter(): void
    {
        $unstarred = $this->classifier->classify($this->signals(
            from_email: 'digest@substack.com',
            headers: ['list-id' => '<some.list.substack.com>'],
        ));

        $starred = $this->classifier->classify($this->signals(
            from_email: 'digest@substack.com',
            headers: ['list-id' => '<some.list.substack.com>'],
            label_ids: ['INBOX', 'STARRED'],
        ));

        $this->assertSame(EmailCategory::NEWSLETTER, $starred->category);
        $this->assertSame($unstarred->score - 30, $starred->score);
    }

    public function testMatchedRulesArePolicyIdsOnlyAndEndWithTheTerminalRule(): void
    {
        $result = $this->classifier->classify($this->signals(
            from_email: 'deals@shop.example',
            subject: 'HUGE SALE NOW!!!',
            label_ids: ['INBOX', 'CATEGORY_PROMOTIONS'],
        ));

        $rules = array_column($result->matched, 'rule');

        $this->assertSame(['subject_shouting', 'gmail_promotions'], $rules);
        $this->assertFalse($result->matched[0]['terminal']);
        $this->assertTrue($result->matched[1]['terminal']);
        $this->assertSame(60, $result->score);
    }

    public function testAutoSubmittedNoIsNotTreatedAsAutomated(): void
    {
        $result = $this->classifier->classify($this->signals(
            from_email: 'jana@example.com',
            headers: ['auto-submitted' => 'no'],
        ));

        $this->assertSame(EmailCategory::PRIORITY, $result->category);
    }

    /**
     * @param array<string, string> $headers
     * @param list<string>          $label_ids
     * @param list<string>          $to_addresses
     */
    private function signals(
        string $from_email,
        string $subject = 'Subject',
        array $headers = [],
        array $label_ids = ['INBOX'],
        array $to_addresses = [],
        bool $user_has_replied_to_sender = false,
    ): TriageSignals {
        return new TriageSignals(
            from_email: $from_email,
            from_name: null,
            subject: $subject,
            headers: $headers,
            label_ids: $label_ids,
            to_addresses: $to_addresses,
            user_has_replied_to_sender: $user_has_replied_to_sender,
            has_in_reply_to: false,
            user_email: 'me@example.com',
        );
    }
}
