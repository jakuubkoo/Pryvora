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
    case SPAM = 'spam';

    /**
     * Everything that is not PRIORITY is noise. The Inbox splits on this.
     */
    public function is_noise(): bool
    {
        return self::PRIORITY !== $this;
    }
}
