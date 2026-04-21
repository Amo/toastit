<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260421110000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add collaborative workspace notes with persisted version history.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE workspace_note (id INT AUTO_INCREMENT NOT NULL, workspace_id INT NOT NULL, author_id INT NOT NULL, title VARCHAR(255) NOT NULL, body LONGTEXT DEFAULT NULL, is_important TINYINT(1) NOT NULL DEFAULT 0, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_D2A9EF8F82D40A1F (workspace_id), INDEX IDX_D2A9EF8FF675F31B (author_id), INDEX workspace_note_display_idx (is_important, updated_at), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE workspace_note_version (id INT AUTO_INCREMENT NOT NULL, note_id INT NOT NULL, author_id INT NOT NULL, title VARCHAR(255) NOT NULL, body LONGTEXT DEFAULT NULL, is_important TINYINT(1) NOT NULL DEFAULT 0, recorded_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_6B3B56B7EDBCA43 (note_id), INDEX IDX_6B3B56BF675F31B (author_id), INDEX workspace_note_version_history_idx (note_id, recorded_at), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE workspace_note ADD CONSTRAINT FK_D2A9EF8F82D40A1F FOREIGN KEY (workspace_id) REFERENCES workspace (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE workspace_note ADD CONSTRAINT FK_D2A9EF8FF675F31B FOREIGN KEY (author_id) REFERENCES `user` (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE workspace_note_version ADD CONSTRAINT FK_6B3B56B7EDBCA43 FOREIGN KEY (note_id) REFERENCES workspace_note (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE workspace_note_version ADD CONSTRAINT FK_6B3B56BF675F31B FOREIGN KEY (author_id) REFERENCES `user` (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE workspace_note_version DROP FOREIGN KEY FK_6B3B56B7EDBCA43');
        $this->addSql('ALTER TABLE workspace_note_version DROP FOREIGN KEY FK_6B3B56BF675F31B');
        $this->addSql('ALTER TABLE workspace_note DROP FOREIGN KEY FK_D2A9EF8F82D40A1F');
        $this->addSql('ALTER TABLE workspace_note DROP FOREIGN KEY FK_D2A9EF8FF675F31B');
        $this->addSql('DROP TABLE workspace_note_version');
        $this->addSql('DROP TABLE workspace_note');
    }
}
