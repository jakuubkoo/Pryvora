<?php

declare(strict_types=1);

namespace App\Enum;

enum EmailCategory: string
{
    case PRIORITY = 'priority';
    case NOTIFICATION = 'notification';
    case NEWSLETTER = 'newsletter';
    case PROMOTION = 'promotion';
    case SOCIAL = 'social';

    /** Gmail's verdict. */
    case SPAM = 'spam';

    /**
     * The user's verdict — they told us to never show this sender again.
     *
     * Deliberately not the same as SPAM. They share the Blocked tab, but only one
     * of them is an accusation Google made, and the "Why?" panel has to be able to
     * tell the user which of the two happened.
     */
    case BLOCKED = 'blocked';

    /**
     * Which of the three Inbox tabs this category belongs to.
     *
     * "Noise" used to mean "anything that is not PRIORITY", which lumped a
     * newsletter the user reads together with a sender they never want to hear
     * from again. Those are different feelings and now they are different tabs.
     */
    public function bucket(): string
    {
        return match ($this) {
            self::PRIORITY => 'needs_you',
            self::SPAM, self::BLOCKED => 'blocked',
            self::NOTIFICATION, self::NEWSLETTER, self::PROMOTION, self::SOCIAL => 'noise',
        };
    }

    /**
     * @return list<self>
     */
    public static function in_bucket(string $bucket): array
    {
        return array_values(array_filter(
            self::cases(),
            static fn (self $case): bool => $case->bucket() === $bucket,
        ));
    }
}
