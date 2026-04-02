<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260402163000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add soft delete timestamp on workspaces';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE team ADD deleted_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
        $this->addSql('CREATE INDEX IDX_C4E0A61A9C6A9B3C ON team (deleted_at)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX IDX_C4E0A61A9C6A9B3C ON team');
        $this->addSql('ALTER TABLE team DROP deleted_at');
    }
}
