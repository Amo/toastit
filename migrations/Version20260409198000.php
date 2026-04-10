<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260409198000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add personal access tokens for public API authentication.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
CREATE TABLE personal_access_token (
    id INT AUTO_INCREMENT NOT NULL,
    user_id INT NOT NULL,
    name VARCHAR(120) NOT NULL,
    selector VARCHAR(16) NOT NULL,
    token_hash VARCHAR(64) NOT NULL,
    created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
    last_used_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)',
    expires_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)',
    revoked_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)',
    INDEX idx_pat_selector (selector),
    INDEX idx_pat_user (user_id),
    PRIMARY KEY(id)
) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
SQL);
        $this->addSql('ALTER TABLE personal_access_token ADD CONSTRAINT FK_11B95B44A76ED395 FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE personal_access_token DROP FOREIGN KEY FK_11B95B44A76ED395');
        $this->addSql('DROP TABLE personal_access_token');
    }
}

