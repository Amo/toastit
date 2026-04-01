<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260401110000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add workspace default due date preset.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE team ADD default_due_preset VARCHAR(32) DEFAULT 'next_week' NOT NULL");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE team DROP default_due_preset');
    }
}
