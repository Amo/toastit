<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260401123000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add optional permalink background URL on workspaces';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE team ADD permalink_background_url VARCHAR(1024) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE team DROP permalink_background_url');
    }
}
