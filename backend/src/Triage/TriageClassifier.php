<?php

declare(strict_types=1);

namespace App\Triage;

use App\Enum\EmailCategory;

/**
 * Decides whether an email needs the user or is noise, from headers and Gmail's
 * own system labels. No model, no network, no message body — the entire policy
 * is the two tables below and is meant to be read top-to-bottom.
 *
 * Provider-neutral by construction: anything that can build TriageSignals (a
 * future IMAP provider) reuses this verbatim.
 */
final class TriageClassifier
{
    /**
     * Senders that are machines. Matched against the local part with any +tag
     * already stripped, so "noreply+abc@x" still hits.
     */
    private const NOREPLY_PATTERN = '/^(no[-_.]?reply|do[-_.]?not[-_.]?reply|donotreply|notifications?|alerts?|mailer[-_.]?daemon|bounce|postmaster|automated|system)$/i';

    private const BULK_PRECEDENCE = ['bulk', 'list', 'junk'];

    public function classify(TriageSignals $signals): TriageResult
    {
        $matched = [];
        $score = 0;

        foreach ($this->score_modifiers($signals) as $rule => $weight) {
            $matched[] = ['rule' => $rule, 'weight' => $weight, 'terminal' => false];
            $score += $weight;
        }

        [$category, $rule, $weight] = $this->categorize($signals);

        $matched[] = ['rule' => $rule, 'weight' => $weight, 'terminal' => true];
        $score += $weight;

        return new TriageResult($category, $score, $matched);
    }

    /**
     * Non-terminal. These never change the category — they only order rows
     * within a bucket, so a starred promotion still sits above a cold one.
     *
     * @return array<string, int>
     */
    private function score_modifiers(TriageSignals $signals): array
    {
        $modifiers = [];

        if ($signals->has_label('STARRED')) {
            $modifiers['starred'] = -30;
        }

        if ($signals->has_label('IMPORTANT')) {
            $modifiers['gmail_important'] = -20;
        }

        if ($this->is_addressed_directly($signals)) {
            $modifiers['addressed_directly'] = -15;
        }

        if ($this->is_shouting($signals->subject)) {
            $modifiers['subject_shouting'] = 10;
        }

        return $modifiers;
    }

    /**
     * Ordered. First match wins and stops.
     *
     * Two orderings carry the whole design:
     *
     * - user_replied_to_sender sits above every header and every Gmail label.
     *   If you have ever replied to someone, their "newsletter" is not noise to
     *   you. This is what makes the classifier feel intelligent without a model.
     *
     * - gmail_updates sits last. CATEGORY_UPDATES is Gmail's junk drawer and
     *   catches a lot of genuinely useful transactional mail, so anything more
     *   specific gets a shot at the message first.
     *
     * @return array{0: EmailCategory, 1: string, 2: int}
     */
    private function categorize(TriageSignals $signals): array
    {
        if ($signals->has_label('SPAM')) {
            return [EmailCategory::SPAM, 'gmail_spam', 100];
        }

        if ($signals->user_has_replied_to_sender) {
            return [EmailCategory::PRIORITY, 'user_replied_to_sender', -80];
        }

        $has_unsubscribe = $signals->has_header('list-unsubscribe');

        if ($has_unsubscribe && $signals->has_label('CATEGORY_PROMOTIONS')) {
            return [EmailCategory::PROMOTION, 'promotions_bulk', 60];
        }

        if ($signals->has_label('CATEGORY_PROMOTIONS')) {
            return [EmailCategory::PROMOTION, 'gmail_promotions', 50];
        }

        // RFC 2919. A List-Id is about as close to proof of a mailing list as
        // email headers get.
        if ($signals->has_header('list-id')) {
            return [EmailCategory::NEWSLETTER, 'list_id_header', 50];
        }

        if ($has_unsubscribe) {
            return [EmailCategory::NEWSLETTER, 'list_unsubscribe_header', 45];
        }

        $auto_submitted = strtolower($signals->header('auto-submitted'));

        if ('' !== $auto_submitted && 'no' !== $auto_submitted) {
            return [EmailCategory::NOTIFICATION, 'auto_submitted', 40];
        }

        if (\in_array(strtolower($signals->header('precedence')), self::BULK_PRECEDENCE, true)) {
            return [EmailCategory::NOTIFICATION, 'precedence_bulk', 35];
        }

        if (1 === preg_match(self::NOREPLY_PATTERN, $signals->sender_local_part())) {
            return [EmailCategory::NOTIFICATION, 'noreply_sender', 35];
        }

        if ($signals->has_label('CATEGORY_SOCIAL')) {
            return [EmailCategory::SOCIAL, 'gmail_social', 30];
        }

        if ($signals->has_label('CATEGORY_FORUMS')) {
            return [EmailCategory::NEWSLETTER, 'gmail_forums', 30];
        }

        if ($signals->has_label('CATEGORY_UPDATES')) {
            return [EmailCategory::NOTIFICATION, 'gmail_updates', 25];
        }

        return [EmailCategory::PRIORITY, 'default_priority', 0];
    }

    /**
     * Mail sent to you by name, rather than to a list you are one of thousands on.
     */
    private function is_addressed_directly(TriageSignals $signals): bool
    {
        if (null === $signals->user_email || '' === $signals->user_email) {
            return false;
        }

        return \in_array(strtolower($signals->user_email), $signals->to_addresses, true);
    }

    /**
     * SHOUTING SUBJECTS!!! Only looks at letters, so "Re: Q3 (2026)" is not
     * flagged for its digits and punctuation.
     */
    private function is_shouting(string $subject): bool
    {
        if (substr_count($subject, '!') >= 3) {
            return true;
        }

        $letters = preg_replace('/[^\p{L}]/u', '', $subject) ?? '';
        $length = mb_strlen($letters);

        if ($length < 4) {
            return false;
        }

        $upper = preg_replace('/[^\p{Lu}]/u', '', $letters) ?? '';

        return mb_strlen($upper) / $length > 0.6;
    }
}
