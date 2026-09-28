<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Self-service sign-up (public page /inscription).
 */
final class Version20260928183757 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Self-service sign-up: account source and review, member password, e-mail verification.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE account ADD source VARCHAR(16) DEFAULT NULL');
        $this->addSql('ALTER TABLE account ADD signup_reviewed_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql('ALTER TABLE account_member ADD password_hash VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE account_member ADD email_verified_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql('ALTER TABLE account_member ADD verification_token_hash VARCHAR(64) DEFAULT NULL');
        $this->addSql('ALTER TABLE account_member ADD verification_expires_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql('CREATE INDEX IDX_57D0340B282BDDD4 ON account_member (verification_token_hash)');
        // members added before the sign-up existed were added by the operator: verified
        $this->addSql('UPDATE account_member SET email_verified_at = NOW() WHERE email_verified_at IS NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE account DROP source');
        $this->addSql('ALTER TABLE account DROP signup_reviewed_at');
        $this->addSql('DROP INDEX IDX_57D0340B282BDDD4');
        $this->addSql('ALTER TABLE account_member DROP password_hash');
        $this->addSql('ALTER TABLE account_member DROP email_verified_at');
        $this->addSql('ALTER TABLE account_member DROP verification_token_hash');
        $this->addSql('ALTER TABLE account_member DROP verification_expires_at');
    }
}
