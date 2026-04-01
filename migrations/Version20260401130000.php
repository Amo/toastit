<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260401130000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create dedicated toasting_session entity and backfill from workspace meeting timestamps.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE toasting_session (id INT AUTO_INCREMENT NOT NULL, workspace_id INT NOT NULL, started_by_id INT NOT NULL, ended_by_id INT DEFAULT NULL, started_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', ended_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_41954DF518E9E06D (workspace_id), INDEX IDX_41954DF59A8F3A1 (started_by_id), INDEX IDX_41954DF5FE8C6DB9 (ended_by_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE toasting_session ADD CONSTRAINT FK_41954DF518E9E06D FOREIGN KEY (workspace_id) REFERENCES team (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE toasting_session ADD CONSTRAINT FK_41954DF59A8F3A1 FOREIGN KEY (started_by_id) REFERENCES user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE toasting_session ADD CONSTRAINT FK_41954DF5FE8C6DB9 FOREIGN KEY (ended_by_id) REFERENCES user (id) ON DELETE SET NULL');
        $this->addSql('INSERT INTO toasting_session (workspace_id, started_by_id, ended_by_id, started_at, ended_at)
            SELECT team.id, team.organizer_id, CASE WHEN team.meeting_ended_at IS NULL THEN NULL ELSE team.organizer_id END, team.meeting_started_at, team.meeting_ended_at
            FROM team
            WHERE team.meeting_started_at IS NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE toasting_session');
    }
}
