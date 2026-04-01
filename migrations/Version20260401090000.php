<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260401090000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Merge team and meeting concepts into workspace meeting mode and link parking lot follow ups.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE team ADD meeting_mode VARCHAR(16) DEFAULT 'idle' NOT NULL, ADD meeting_started_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)', ADD meeting_ended_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)'");
        $this->addSql("ALTER TABLE parking_lot_item CHANGE meeting_id meeting_id INT DEFAULT NULL");
        $this->addSql("ALTER TABLE parking_lot_item ADD previous_item_id INT DEFAULT NULL");
        $this->addSql('ALTER TABLE parking_lot_item ADD CONSTRAINT FK_EDBF6A6CF9C94C7D FOREIGN KEY (previous_item_id) REFERENCES parking_lot_item (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_EDBF6A6CF9C94C7D ON parking_lot_item (previous_item_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE parking_lot_item DROP FOREIGN KEY FK_EDBF6A6CF9C94C7D');
        $this->addSql('DROP INDEX IDX_EDBF6A6CF9C94C7D ON parking_lot_item');
        $this->addSql('ALTER TABLE parking_lot_item DROP previous_item_id');
        $this->addSql('ALTER TABLE parking_lot_item CHANGE meeting_id meeting_id INT NOT NULL');
        $this->addSql('ALTER TABLE team DROP meeting_mode, DROP meeting_started_at, DROP meeting_ended_at');
    }
}
