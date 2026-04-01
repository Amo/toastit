<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260401114500 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Track when a toast becomes vetoed or toasted.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE parking_lot_item ADD status_changed_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)'");
        $this->addSql("UPDATE parking_lot_item SET status_changed_at = created_at WHERE status = 'vetoed' OR discussion_status = 'treated'");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE parking_lot_item DROP status_changed_at');
    }
}
