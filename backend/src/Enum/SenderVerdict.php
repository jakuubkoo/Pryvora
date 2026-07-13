<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * What the user has decided about a sender, overriding whatever the classifier
 * makes of any individual message from them.
 */
enum SenderVerdict: string
{
    /** Always put this sender in front of me, whatever the headers say. */
    case ALWAYS_SHOW = 'always_show';

    /** Never show me this sender again. Hides them in Pryvora; Gmail is untouched. */
    case BLOCKED = 'blocked';
}
