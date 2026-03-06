<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260222114122 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        // Add column as nullable first
        $this->addSql('ALTER TABLE "user" ADD two_factor_enabled BOOLEAN DEFAULT FALSE');
        // Set default value for existing rows
        $this->addSql('UPDATE "user" SET two_factor_enabled = FALSE WHERE two_factor_enabled IS NULL');
        // Make column NOT NULL
        $this->addSql('ALTER TABLE "user" ALTER COLUMN two_factor_enabled SET NOT NULL');

        $this->addSql('ALTER TABLE "user" ADD two_factor_secret VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE "user" ADD two_factor_confirmed_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE "user" DROP two_factor_enabled');
        $this->addSql('ALTER TABLE "user" DROP two_factor_secret');
        $this->addSql('ALTER TABLE "user" DROP two_factor_confirmed_at');
    }
}
