<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260401100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Flag default workspaces explicitly.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE team ADD is_default TINYINT(1) DEFAULT 0 NOT NULL');
        $this->addSql("UPDATE team SET is_default = 1 WHERE name = 'My Toasts'");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE team DROP is_default');
    }
}
