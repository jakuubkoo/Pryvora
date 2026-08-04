<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260804160000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Merge the gmail provider into a single google account that also syncs Google Calendar.';
    }

    /**
     * Renaming in place rather than asking people to reconnect: disconnecting an
     * account cascade-deletes every message it imported, so a reconnect would
     * throw away a completed backfill to gain nothing.
     *
     * The old flat sync state becomes the 'gmail' slice, and the calendar slice
     * starts empty so the first sync after this enumerates every calendar. The
     * calendar half stays dormant until the user reconnects and grants the
     * calendar scope — GoogleProvider::sync_calendar checks the stored scopes.
     */
    public function up(Schema $schema): void
    {
        $this->addSql("UPDATE connected_account SET provider = 'google', sync_state = json_build_object('gmail', COALESCE(sync_state, '{}'::json), 'calendar', '{}'::json) WHERE provider = 'gmail'");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("UPDATE connected_account SET provider = 'gmail', sync_state = COALESCE(sync_state -> 'gmail', '{}'::json) WHERE provider = 'google'");
    }
}
