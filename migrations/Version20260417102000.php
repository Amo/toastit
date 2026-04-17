<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260417102000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add per-user advanced xAI model preference.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE user ADD advanced_ai_model_enabled TINYINT(1) NOT NULL DEFAULT 0');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE user DROP advanced_ai_model_enabled');
    }
}
