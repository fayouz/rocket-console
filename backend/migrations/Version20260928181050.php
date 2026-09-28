<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260928181050 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Rocket Console 0.1.0: catalogue, accounts, members, subscriptions, audit log';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE account (id UUID NOT NULL, slug VARCHAR(64) NOT NULL, name VARCHAR(120) NOT NULL, status VARCHAR(12) NOT NULL, trial_ends_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, billing_name VARCHAR(120) DEFAULT NULL, billing_email VARCHAR(180) DEFAULT NULL, billing_address VARCHAR(255) DEFAULT NULL, country VARCHAR(2) DEFAULT NULL, vat_number VARCHAR(32) DEFAULT NULL, notes TEXT DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, created_by VARCHAR(180) DEFAULT NULL, updated_by VARCHAR(180) DEFAULT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_7D3656A4989D9B62 ON account (slug)');
        $this->addSql('CREATE TABLE account_member (id UUID NOT NULL, email VARCHAR(180) NOT NULL, name VARCHAR(120) DEFAULT NULL, role VARCHAR(8) NOT NULL, account_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_57D0340B9B6B5FBAE7927C74 ON account_member (account_id, email)');
        $this->addSql('CREATE INDEX IDX_57D0340B9B6B5FBA ON account_member (account_id)');
        $this->addSql('CREATE TABLE audit_log (id UUID NOT NULL, occurred_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, actor VARCHAR(180) DEFAULT NULL, action VARCHAR(60) NOT NULL, subject_type VARCHAR(40) NOT NULL, subject_id VARCHAR(64) NOT NULL, account_slug VARCHAR(64) DEFAULT NULL, data JSON NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_F6E1C0F5140E1CC8 ON audit_log (account_slug)');
        $this->addSql('CREATE INDEX IDX_F6E1C0F587C03D1B ON audit_log (occurred_at)');
        $this->addSql('CREATE TABLE catalogue_brick (id UUID NOT NULL, code VARCHAR(40) NOT NULL, name VARCHAR(80) NOT NULL, family VARCHAR(12) NOT NULL, depends JSON NOT NULL, unit VARCHAR(16) NOT NULL, monthly_price_cents INT NOT NULL, description VARCHAR(500) DEFAULT NULL, position INT NOT NULL, active BOOLEAN NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, created_by VARCHAR(180) DEFAULT NULL, updated_by VARCHAR(180) DEFAULT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_2F4CB98D77153098 ON catalogue_brick (code)');
        $this->addSql('CREATE TABLE catalogue_option (id UUID NOT NULL, code VARCHAR(40) NOT NULL, name VARCHAR(80) NOT NULL, brick VARCHAR(40) NOT NULL, grants VARCHAR(40) DEFAULT NULL, unit VARCHAR(16) NOT NULL, monthly_price_cents INT NOT NULL, active BOOLEAN NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, created_by VARCHAR(180) DEFAULT NULL, updated_by VARCHAR(180) DEFAULT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_E6F89FD877153098 ON catalogue_option (code)');
        $this->addSql('CREATE TABLE catalogue_plan (id UUID NOT NULL, code VARCHAR(40) NOT NULL, name VARCHAR(80) NOT NULL, bricks JSON NOT NULL, unit VARCHAR(16) NOT NULL, unit_price_cents INT NOT NULL, description VARCHAR(500) DEFAULT NULL, active BOOLEAN NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, created_by VARCHAR(180) DEFAULT NULL, updated_by VARCHAR(180) DEFAULT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_766F9DAA77153098 ON catalogue_plan (code)');
        $this->addSql('CREATE TABLE catalogue_pricing (id INT NOT NULL, minimum_monthly_cents INT NOT NULL, volume_tiers JSON NOT NULL, yearly_free_months INT NOT NULL, trial_days INT NOT NULL, currency VARCHAR(3) NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, created_by VARCHAR(180) DEFAULT NULL, updated_by VARCHAR(180) DEFAULT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE TABLE subscription (id UUID NOT NULL, plan VARCHAR(40) DEFAULT NULL, bricks JSON NOT NULL, options JSON NOT NULL, quantities JSON NOT NULL, period VARCHAR(8) NOT NULL, starts_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, ends_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, created_by VARCHAR(180) DEFAULT NULL, updated_by VARCHAR(180) DEFAULT NULL, account_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_A3C664D39B6B5FBA ON subscription (account_id)');
        $this->addSql('ALTER TABLE account_member ADD CONSTRAINT FK_57D0340B9B6B5FBA FOREIGN KEY (account_id) REFERENCES account (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE subscription ADD CONSTRAINT FK_A3C664D39B6B5FBA FOREIGN KEY (account_id) REFERENCES account (id) ON DELETE CASCADE NOT DEFERRABLE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE account_member DROP CONSTRAINT FK_57D0340B9B6B5FBA');
        $this->addSql('ALTER TABLE subscription DROP CONSTRAINT FK_A3C664D39B6B5FBA');
        $this->addSql('DROP TABLE account');
        $this->addSql('DROP TABLE account_member');
        $this->addSql('DROP TABLE audit_log');
        $this->addSql('DROP TABLE catalogue_brick');
        $this->addSql('DROP TABLE catalogue_option');
        $this->addSql('DROP TABLE catalogue_plan');
        $this->addSql('DROP TABLE catalogue_pricing');
        $this->addSql('DROP TABLE subscription');
    }
}
