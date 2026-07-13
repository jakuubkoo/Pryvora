<?php

declare(strict_types=1);

namespace App\Tests\Triage;

use App\Enum\EmailCategory;
use App\Enum\SenderVerdict;
use App\Triage\EffectiveCategory;
use PHPUnit\Framework\TestCase;

class EffectiveCategoryTest extends TestCase
{
    private EffectiveCategory $resolver;

    protected function setUp(): void
    {
        $this->resolver = new EffectiveCategory();
    }

    public function testWithNoRuleTheClassifierVerdictStands(): void
    {
        foreach (EmailCategory::cases() as $case) {
            $this->assertSame($case, $this->resolver->resolve($case, null));
        }
    }

    public function testBlockingASenderBeatsEveryClassifierVerdict(): void
    {
        foreach (EmailCategory::cases() as $case) {
            $this->assertSame(
                EmailCategory::BLOCKED,
                $this->resolver->resolve($case, SenderVerdict::BLOCKED),
                'Blocking is always safe and must always win.',
            );
        }
    }

    public function testAlwaysShowLiftsNoiseIntoPriority(): void
    {
        $noise = [
            EmailCategory::NEWSLETTER,
            EmailCategory::NOTIFICATION,
            EmailCategory::PROMOTION,
            EmailCategory::SOCIAL,
        ];

        foreach ($noise as $case) {
            $this->assertSame(EmailCategory::PRIORITY, $this->resolver->resolve($case, SenderVerdict::ALWAYS_SHOW));
        }
    }

    /**
     * The most important assertion here.
     *
     * A From: header is trivially forged. If "always show" could pull mail out of
     * SPAM, an attacker who learned an address the user trusts could spoof it and
     * walk into the priority inbox. Blocking wins in both directions; un-blocking
     * does not.
     */
    public function testAlwaysShowCannotRescueMailGmailCalledSpam(): void
    {
        $this->assertSame(
            EmailCategory::SPAM,
            $this->resolver->resolve(EmailCategory::SPAM, SenderVerdict::ALWAYS_SHOW),
        );
    }

    public function testAlwaysShowOnAlreadyPriorityMailIsANoop(): void
    {
        $this->assertSame(
            EmailCategory::PRIORITY,
            $this->resolver->resolve(EmailCategory::PRIORITY, SenderVerdict::ALWAYS_SHOW),
        );
    }

    /**
     * The tab each category lands in. "Noise" used to mean "not priority", which
     * put a newsletter and a blocked sender in the same drawer.
     */
    public function testCategoriesBucketIntoTheThreeTabs(): void
    {
        $this->assertSame('needs_you', EmailCategory::PRIORITY->bucket());

        $this->assertSame('noise', EmailCategory::NEWSLETTER->bucket());
        $this->assertSame('noise', EmailCategory::NOTIFICATION->bucket());
        $this->assertSame('noise', EmailCategory::PROMOTION->bucket());
        $this->assertSame('noise', EmailCategory::SOCIAL->bucket());

        $this->assertSame('blocked', EmailCategory::SPAM->bucket());
        $this->assertSame('blocked', EmailCategory::BLOCKED->bucket());
    }

    public function testInBucketReturnsEveryCategoryInThatTab(): void
    {
        $this->assertSame(
            [EmailCategory::NOTIFICATION, EmailCategory::NEWSLETTER, EmailCategory::PROMOTION, EmailCategory::SOCIAL],
            EmailCategory::in_bucket('noise'),
        );

        $this->assertSame(
            [EmailCategory::SPAM, EmailCategory::BLOCKED],
            EmailCategory::in_bucket('blocked'),
        );
    }
}
