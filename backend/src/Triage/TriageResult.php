<?php

declare(strict_types=1);

namespace App\Triage;

use App\Enum\EmailCategory;

/**
 * $matched is persisted verbatim into EmailMessage::categoryReason and is what
 * the "Why?" popover renders.
 *
 * It holds rule ids and weights only — never matched content. That is the whole
 * reason the column can be plaintext. See the note on EmailMessage::$categoryReason.
 */
final readonly class TriageResult
{
    /**
     * @param list<array{rule: string, weight: int, terminal: bool}> $matched
     */
    public function __construct(
        public EmailCategory $category,
        public int $score,
        public array $matched,
    ) {
    }

    /**
     * The rule that decided the category, i.e. the terminal one.
     */
    public function deciding_rule(): ?string
    {
        foreach ($this->matched as $entry) {
            if ($entry['terminal']) {
                return $entry['rule'];
            }
        }

        return null;
    }
}
