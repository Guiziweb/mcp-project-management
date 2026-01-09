<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260106180614 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Remove provider_type column from organization (Redmine only)';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TEMPORARY TABLE __temp__organization AS SELECT id, name, slug, size, provider_config, enabled_tools, created_at FROM organization');
        $this->addSql('DROP TABLE organization');
        $this->addSql('CREATE TABLE organization (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, name VARCHAR(255) NOT NULL, slug VARCHAR(255) NOT NULL, size VARCHAR(20) DEFAULT NULL, provider_config CLOB NOT NULL --(DC2Type:json)
        , enabled_tools CLOB NOT NULL --(DC2Type:json)
        , created_at DATETIME NOT NULL --(DC2Type:datetime_immutable)
        )');
        $this->addSql('INSERT INTO organization (id, name, slug, size, provider_config, enabled_tools, created_at) SELECT id, name, slug, size, provider_config, enabled_tools, created_at FROM __temp__organization');
        $this->addSql('DROP TABLE __temp__organization');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_C1EE637C989D9B62 ON organization (slug)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE organization ADD COLUMN provider_type VARCHAR(50) NOT NULL');
    }
}
