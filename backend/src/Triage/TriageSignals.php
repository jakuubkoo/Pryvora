<?php

declare(strict_types=1);

namespace App\Triage;

/**
 * Everything the classifier is allowed to look at. Assembling this is the
 * caller's job — it needs I/O (the sender-reputation set comes from the user's
 * sent mail), and keeping that out here is what makes classify() a pure
 * function that unit-tests without a container.
 *
 * Message bodies are deliberately absent: the Gmail client fetches with
 * format=METADATA and never receives one.
 */
final readonly class TriageSignals
{
    /**
     * @param string                $from_email                 lowercased
     * @param array<string, string> $headers                    lowercased keys
     * @param list<string>          $label_ids                  Gmail system labels only
     * @param list<string>          $to_addresses               lowercased
     * @param bool                  $user_has_replied_to_sender the sender is in the user's own reply history
     */
    public function __construct(
        public string $from_email,
        public ?string $from_name,
        public string $subject,
        public array $headers,
        public array $label_ids,
        public array $to_addresses,
        public bool $user_has_replied_to_sender,
        public bool $has_in_reply_to,
        public ?string $user_email = null,
    ) {
    }

    public function has_header(string $name): bool
    {
        $value = $this->headers[$name] ?? '';

        return '' !== trim($value);
    }

    public function header(string $name): string
    {
        return trim($this->headers[$name] ?? '');
    }

    public function has_label(string $label): bool
    {
        return \in_array($label, $this->label_ids, true);
    }

    /**
     * The part of the sender address before the '@', with any +tag stripped.
     */
    public function sender_local_part(): string
    {
        $at = strpos($this->from_email, '@');
        $local = false === $at ? $this->from_email : substr($this->from_email, 0, $at);
        $plus = strpos($local, '+');

        return false === $plus ? $local : substr($local, 0, $plus);
    }
}
