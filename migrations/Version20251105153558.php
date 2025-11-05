<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251105153558 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        unset($schema);

        $this->createWebhooksTable();
        $this->createMessengerMessagesTable();
        $this->createMessengerIndexes();
    }

    public function down(Schema $schema): void
    {
        unset($schema);

        // Auto-generated migration stub. Adjust as needed.
        $this->addSql('DROP TABLE webhooks');
        $this->addSql('DROP TABLE messenger_messages');
    }

    private function createWebhooksTable(): void
    {
        $this->addSql(
            <<<'SQL'
CREATE TABLE webhooks (
    id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
    session_id VARCHAR(255) NOT NULL,
    method VARCHAR(10) NOT NULL,
    headers CLOB NOT NULL,
    body CLOB DEFAULT NULL,
    created_at DATETIME NOT NULL
)
SQL
        );
        $this->addSql('CREATE INDEX idx_session_id ON webhooks (session_id)');
    }

    private function createMessengerMessagesTable(): void
    {
        $this->addSql(
            <<<'SQL'
CREATE TABLE messenger_messages (
    id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
    body CLOB NOT NULL,
    headers CLOB NOT NULL,
    queue_name VARCHAR(190) NOT NULL,
    created_at DATETIME NOT NULL,
    available_at DATETIME NOT NULL,
    delivered_at DATETIME DEFAULT NULL
)
SQL
        );
    }

    private function createMessengerIndexes(): void
    {
        $this->addSql(
            <<<'SQL'
CREATE INDEX IDX_75EA56E0FB7336F0 ON messenger_messages (queue_name)
SQL
        );
        $this->addSql(
            <<<'SQL'
CREATE INDEX IDX_75EA56E0E3BD61CE ON messenger_messages (available_at)
SQL
        );
        $this->addSql(
            <<<'SQL'
CREATE INDEX IDX_75EA56E016BA31DB ON messenger_messages (delivered_at)
SQL
        );
    }
}
