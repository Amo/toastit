<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260401113000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Support multiple workspace owners through memberships.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE team_member ADD is_owner TINYINT(1) DEFAULT 0 NOT NULL');
        $this->addSql('UPDATE team_member tm INNER JOIN team t ON tm.team_id = t.id SET tm.is_owner = 1 WHERE tm.user_id = t.organizer_id');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE team_member DROP is_owner');
    }
}
