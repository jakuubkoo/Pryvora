<?php

declare(strict_types=1);

namespace App\Triage;

use App\Enum\EmailCategory;
use App\Enum\SenderVerdict;

/**
 * Lays the user's standing decision about a sender over the classifier's verdict
 * about a message.
 *
 * This is deliberately NOT part of TriageClassifier. If a user rule were just two
 * more rows at the top of that rule table, it would overwrite the classifier's
 * verdict and un-blocking a sender could never restore it — raw headers are never
 * stored, so there would be nothing left to recompute from. Keeping the two
 * separate is what makes "unblock" put a newsletter back in Noise rather than
 * dumping it in Priority.
 */
final class EffectiveCategory
{
    public function resolve(EmailCategory $auto, ?SenderVerdict $rule): EmailCategory
    {
        if (null === $rule) {
            return $auto;
        }

        if (SenderVerdict::BLOCKED === $rule) {
            return EmailCategory::BLOCKED;
        }

        // ALWAYS_SHOW does not override Gmail's spam verdict, and this asymmetry is
        // the point rather than an oversight.
        //
        // A From: header is trivially forged. If "always show this sender" could
        // pull mail out of SPAM, then anyone who learned an address the user trusts
        // could spoof it and land straight in the priority inbox — the rule meant
        // to help them would be the thing that hurt them.
        //
        // Blocking is safe in both directions and always wins. Un-blocking is not.
        if (EmailCategory::SPAM === $auto) {
            return EmailCategory::SPAM;
        }

        return EmailCategory::PRIORITY;
    }
}
