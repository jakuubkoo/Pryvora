<?php

declare(strict_types=1);

namespace App\Message;

/**
 * Carries scalars rather than an event id: by the time this runs the local row
 * is already gone, so the href and etag have to be captured before the delete.
 */
final readonly class DeleteRemoteCalendarEvent
{
    public function __construct(
        public int $connected_account_id,
        public string $external_href,
        public ?string $external_etag,
    ) {
    }
}
