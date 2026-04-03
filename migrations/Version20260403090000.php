<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260403090000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Persist AI editable summaries on toasting sessions.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE toasting_session ADD summary LONGTEXT DEFAULT NULL, ADD summary_generated_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)', ADD summary_updated_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)'");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE toasting_session DROP summary, DROP summary_generated_at, DROP summary_updated_at');
    }
}
