<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260401124500 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add solo workspace flag and backfill default workspaces as solo.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE team ADD is_solo_workspace TINYINT(1) NOT NULL DEFAULT 0');
        $this->addSql('UPDATE team SET is_solo_workspace = 1 WHERE is_default = 1');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE team DROP is_solo_workspace');
    }
}
