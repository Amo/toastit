<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260409103000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add user-level inbound xAI auto-apply preferences with default ON for all actions.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE user ADD inbound_auto_apply_reword TINYINT(1) NOT NULL DEFAULT 1, ADD inbound_auto_apply_assignee TINYINT(1) NOT NULL DEFAULT 1, ADD inbound_auto_apply_due_date TINYINT(1) NOT NULL DEFAULT 1, ADD inbound_auto_apply_workspace TINYINT(1) NOT NULL DEFAULT 1');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE user DROP inbound_auto_apply_reword, DROP inbound_auto_apply_assignee, DROP inbound_auto_apply_due_date, DROP inbound_auto_apply_workspace');
    }
}
