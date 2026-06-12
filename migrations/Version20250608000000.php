<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20250608000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create accounts, transfers, and ledger_entries tables';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE accounts (
                id BINARY(16) NOT NULL COMMENT '(DC2Type:uuid)',
                account_number VARCHAR(34) NOT NULL,
                balance DECIMAL(19,4) NOT NULL DEFAULT 0.0000,
                currency CHAR(3) NOT NULL,
                status VARCHAR(20) NOT NULL,
                created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
                updated_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
                version INT NOT NULL DEFAULT 1,
                UNIQUE INDEX UNIQ_ACCOUNTS_ACCOUNT_NUMBER (account_number),
                INDEX idx_accounts_account_number (account_number),
                INDEX idx_accounts_status (status),
                PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);

        $this->addSql(<<<'SQL'
            CREATE TABLE transfers (
                id BINARY(16) NOT NULL COMMENT '(DC2Type:uuid)',
                source_account_id BINARY(16) NOT NULL COMMENT '(DC2Type:uuid)',
                destination_account_id BINARY(16) NOT NULL COMMENT '(DC2Type:uuid)',
                amount DECIMAL(19,4) NOT NULL,
                currency CHAR(3) NOT NULL,
                status VARCHAR(20) NOT NULL,
                idempotency_key VARCHAR(128) NOT NULL,
                request_hash VARCHAR(64) NOT NULL,
                failure_reason VARCHAR(500) DEFAULT NULL,
                created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
                completed_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)',
                UNIQUE INDEX uniq_transfers_idempotency_key (idempotency_key),
                INDEX idx_transfers_source_account (source_account_id),
                INDEX idx_transfers_destination_account (destination_account_id),
                INDEX idx_transfers_status (status),
                INDEX idx_transfers_created_at (created_at),
                PRIMARY KEY(id),
                CONSTRAINT FK_TRANSFERS_SOURCE FOREIGN KEY (source_account_id) REFERENCES accounts (id),
                CONSTRAINT FK_TRANSFERS_DESTINATION FOREIGN KEY (destination_account_id) REFERENCES accounts (id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);

        $this->addSql(<<<'SQL'
            CREATE TABLE ledger_entries (
                id BINARY(16) NOT NULL COMMENT '(DC2Type:uuid)',
                transfer_id BINARY(16) NOT NULL COMMENT '(DC2Type:uuid)',
                account_id BINARY(16) NOT NULL COMMENT '(DC2Type:uuid)',
                type VARCHAR(10) NOT NULL,
                amount DECIMAL(19,4) NOT NULL,
                balance_after DECIMAL(19,4) NOT NULL,
                created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
                INDEX idx_ledger_transfer (transfer_id),
                INDEX idx_ledger_account (account_id),
                INDEX idx_ledger_created_at (created_at),
                PRIMARY KEY(id),
                CONSTRAINT FK_LEDGER_TRANSFER FOREIGN KEY (transfer_id) REFERENCES transfers (id),
                CONSTRAINT FK_LEDGER_ACCOUNT FOREIGN KEY (account_id) REFERENCES accounts (id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE ledger_entries');
        $this->addSql('DROP TABLE transfers');
        $this->addSql('DROP TABLE accounts');
    }
}
