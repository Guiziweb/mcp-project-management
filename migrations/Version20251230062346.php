<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251230062346 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Initial schema: organization, user, access_token, invite_link, mcp_session';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE access_token (id BLOB NOT NULL --(DC2Type:uuid)
        , user_id INTEGER NOT NULL, organization_id INTEGER NOT NULL, parent_token_id BLOB DEFAULT NULL --(DC2Type:uuid)
        , token_hash VARCHAR(64) NOT NULL, credentials CLOB NOT NULL, type VARCHAR(20) NOT NULL, created_at DATETIME NOT NULL --(DC2Type:datetime_immutable)
        , expires_at DATETIME NOT NULL --(DC2Type:datetime_immutable)
        , revoked_at DATETIME DEFAULT NULL --(DC2Type:datetime_immutable)
        , last_used_at DATETIME DEFAULT NULL --(DC2Type:datetime_immutable)
        , client_info VARCHAR(255) DEFAULT NULL, PRIMARY KEY(id), CONSTRAINT FK_B6A2DD68A76ED395 FOREIGN KEY (user_id) REFERENCES app_user (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_B6A2DD6832C8A3DE FOREIGN KEY (organization_id) REFERENCES organization (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_B6A2DD68DE134B68 FOREIGN KEY (parent_token_id) REFERENCES access_token (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_B6A2DD68B3BC57DA ON access_token (token_hash)');
        $this->addSql('CREATE INDEX IDX_B6A2DD68A76ED395 ON access_token (user_id)');
        $this->addSql('CREATE INDEX IDX_B6A2DD68DE134B68 ON access_token (parent_token_id)');
        $this->addSql('CREATE INDEX idx_token_hash ON access_token (token_hash)');
        $this->addSql('CREATE INDEX idx_expires_at ON access_token (expires_at)');
        $this->addSql('CREATE INDEX idx_organization ON access_token (organization_id)');
        $this->addSql('CREATE TABLE app_user (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, organization_id INTEGER NOT NULL, email VARCHAR(255) NOT NULL, google_id VARCHAR(255) NOT NULL, name VARCHAR(255) DEFAULT NULL, roles CLOB NOT NULL --(DC2Type:json)
        , provider_credentials CLOB DEFAULT NULL, enabled_tools CLOB NOT NULL --(DC2Type:json)
        , status VARCHAR(20) NOT NULL, created_at DATETIME NOT NULL --(DC2Type:datetime_immutable)
        , last_seen_at DATETIME DEFAULT NULL --(DC2Type:datetime_immutable)
        , CONSTRAINT FK_88BDF3E932C8A3DE FOREIGN KEY (organization_id) REFERENCES organization (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_88BDF3E9E7927C74 ON app_user (email)');
        $this->addSql('CREATE INDEX IDX_88BDF3E932C8A3DE ON app_user (organization_id)');
        $this->addSql('CREATE TABLE invite_link (token BLOB NOT NULL --(DC2Type:uuid)
        , organization_id INTEGER NOT NULL, created_by_id INTEGER NOT NULL, label VARCHAR(255) DEFAULT NULL, expires_at DATETIME NOT NULL --(DC2Type:datetime_immutable)
        , max_uses INTEGER DEFAULT NULL, uses_count INTEGER NOT NULL, active BOOLEAN NOT NULL, created_at DATETIME NOT NULL --(DC2Type:datetime_immutable)
        , PRIMARY KEY(token), CONSTRAINT FK_2E98587B32C8A3DE FOREIGN KEY (organization_id) REFERENCES organization (id) NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_2E98587BB03A8386 FOREIGN KEY (created_by_id) REFERENCES app_user (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX IDX_2E98587B32C8A3DE ON invite_link (organization_id)');
        $this->addSql('CREATE INDEX IDX_2E98587BB03A8386 ON invite_link (created_by_id)');
        $this->addSql('CREATE TABLE mcp_session (id BLOB NOT NULL --(DC2Type:uuid)
        , user_id INTEGER NOT NULL, organization_id INTEGER NOT NULL, data CLOB NOT NULL, client_info VARCHAR(255) DEFAULT NULL, created_at DATETIME NOT NULL --(DC2Type:datetime_immutable)
        , last_activity_at DATETIME NOT NULL --(DC2Type:datetime_immutable)
        , PRIMARY KEY(id), CONSTRAINT FK_FB9AFDB5A76ED395 FOREIGN KEY (user_id) REFERENCES app_user (id) NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_FB9AFDB532C8A3DE FOREIGN KEY (organization_id) REFERENCES organization (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX IDX_FB9AFDB5A76ED395 ON mcp_session (user_id)');
        $this->addSql('CREATE INDEX idx_session_last_activity ON mcp_session (last_activity_at)');
        $this->addSql('CREATE INDEX idx_session_organization ON mcp_session (organization_id)');
        $this->addSql('CREATE TABLE organization (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, name VARCHAR(255) NOT NULL, slug VARCHAR(255) NOT NULL, provider_type VARCHAR(50) NOT NULL, size VARCHAR(20) DEFAULT NULL, provider_config CLOB NOT NULL --(DC2Type:json)
        , enabled_tools CLOB NOT NULL --(DC2Type:json)
        , created_at DATETIME NOT NULL --(DC2Type:datetime_immutable)
        )');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_C1EE637C989D9B62 ON organization (slug)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE access_token');
        $this->addSql('DROP TABLE app_user');
        $this->addSql('DROP TABLE invite_link');
        $this->addSql('DROP TABLE mcp_session');
        $this->addSql('DROP TABLE organization');
    }
}
