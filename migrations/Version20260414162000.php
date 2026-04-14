<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260414162000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Store optional user preferred timezone for frontend and email date rendering.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE user ADD preferred_timezone VARCHAR(64) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE user DROP preferred_timezone');
    }
}
