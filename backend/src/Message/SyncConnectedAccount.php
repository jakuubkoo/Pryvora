<?php

declare(strict_types=1);

namespace App\Message;

/**
 * Carries the id only. The entity would drag decrypted credentials into the
 * serialized payload that sits in the queue table.
 */
final readonly class SyncConnectedAccount
{
    public function __construct(public int $connected_account_id)
    {
    }
}
