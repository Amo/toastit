<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260331201500 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add API refresh token storage';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE api_refresh_token (id INT AUTO_INCREMENT NOT NULL, user_id INT NOT NULL, token_hash VARCHAR(255) NOT NULL, expires_at DATETIME NOT NULL, last_used_at DATETIME NOT NULL, created_at DATETIME NOT NULL, INDEX IDX_B3D0E76EA76ED395 (user_id), INDEX idx_api_refresh_token_hash (token_hash), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE api_refresh_token ADD CONSTRAINT FK_B3D0E76EA76ED395 FOREIGN KEY (user_id) REFERENCES `user` (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE api_refresh_token DROP FOREIGN KEY FK_B3D0E76EA76ED395');
        $this->addSql('DROP TABLE api_refresh_token');
    }
}
