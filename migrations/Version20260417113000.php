<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260417113000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Drop legacy AI prompt tables now that runtime prompts are file-backed only.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE ai_prompt_version DROP FOREIGN KEY FK_1132EC8A5BA6AF4C');
        $this->addSql('ALTER TABLE ai_prompt_version DROP FOREIGN KEY FK_1132EC8A8C03F15C');
        $this->addSql('DROP TABLE ai_prompt_version');
        $this->addSql('DROP TABLE ai_prompt');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('CREATE TABLE ai_prompt (id INT AUTO_INCREMENT NOT NULL, code VARCHAR(120) NOT NULL, label VARCHAR(180) NOT NULL, description LONGTEXT DEFAULT NULL, available_variables JSON NOT NULL, available_user_variables JSON NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', UNIQUE INDEX uniq_ai_prompt_code (code), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE ai_prompt_version (id INT AUTO_INCREMENT NOT NULL, prompt_id INT NOT NULL, changed_by_user_id INT DEFAULT NULL, version_number INT NOT NULL, system_prompt LONGTEXT NOT NULL, user_prompt_template LONGTEXT NOT NULL, changed_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', UNIQUE INDEX uniq_ai_prompt_version_number (prompt_id, version_number), INDEX IDX_1132EC8A5BA6AF4C (prompt_id), INDEX IDX_1132EC8A8C03F15C (changed_by_user_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE ai_prompt_version ADD CONSTRAINT FK_1132EC8A5BA6AF4C FOREIGN KEY (prompt_id) REFERENCES ai_prompt (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE ai_prompt_version ADD CONSTRAINT FK_1132EC8A8C03F15C FOREIGN KEY (changed_by_user_id) REFERENCES user (id) ON DELETE SET NULL');
    }
}
