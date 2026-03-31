<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260331190000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add meeting lifecycle and discussion follow-up fields';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE meeting ADD status VARCHAR(16) NOT NULL DEFAULT 'scheduled', ADD started_at DATETIME DEFAULT NULL, ADD closed_at DATETIME DEFAULT NULL");
        $this->addSql("ALTER TABLE parking_lot_item ADD discussion_status VARCHAR(16) NOT NULL DEFAULT 'pending', ADD discussion_notes LONGTEXT DEFAULT NULL, ADD follow_up LONGTEXT DEFAULT NULL, ADD owner_id INT DEFAULT NULL, ADD due_at DATETIME DEFAULT NULL");
        $this->addSql('ALTER TABLE parking_lot_item ADD CONSTRAINT FK_EDBF6A6C7E3C61F9 FOREIGN KEY (owner_id) REFERENCES `user` (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_EDBF6A6C7E3C61F9 ON parking_lot_item (owner_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE parking_lot_item DROP FOREIGN KEY FK_EDBF6A6C7E3C61F9');
        $this->addSql('DROP INDEX IDX_EDBF6A6C7E3C61F9 ON parking_lot_item');
        $this->addSql('ALTER TABLE parking_lot_item DROP discussion_status, DROP discussion_notes, DROP follow_up, DROP owner_id, DROP due_at');
        $this->addSql('ALTER TABLE meeting DROP status, DROP started_at, DROP closed_at');
    }
}
