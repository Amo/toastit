<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260331193000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Store structured follow-up items and switch due dates to date only';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE parking_lot_item ADD follow_up_items JSON NOT NULL COMMENT '(DC2Type:json)'");
        $this->addSql("UPDATE parking_lot_item SET follow_up_items = '[]'");
        $this->addSql('ALTER TABLE parking_lot_item CHANGE due_at due_at DATE DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE parking_lot_item DROP follow_up_items');
        $this->addSql('ALTER TABLE parking_lot_item CHANGE due_at due_at DATETIME DEFAULT NULL');
    }
}
