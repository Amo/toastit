<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260409195000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add inbound reword language preference on user profile.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE user ADD inbound_reword_language VARCHAR(8) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE user DROP inbound_reword_language');
    }
}
