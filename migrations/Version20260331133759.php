<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260331133759 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE login_challenge (id INT AUTO_INCREMENT NOT NULL, selector VARCHAR(32) NOT NULL, code VARCHAR(6) NOT NULL, token_hash VARCHAR(255) NOT NULL, purpose VARCHAR(32) NOT NULL, expires_at DATETIME NOT NULL, used_at DATETIME DEFAULT NULL, created_at DATETIME NOT NULL, user_id INT NOT NULL, INDEX IDX_B797A3A4A76ED395 (user_id), INDEX idx_login_challenge_selector (selector), INDEX idx_login_challenge_code (code), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE `user` (id INT AUTO_INCREMENT NOT NULL, email VARCHAR(180) NOT NULL, created_at DATETIME NOT NULL, pin_hash VARCHAR(255) DEFAULT NULL, pin_set_at DATETIME DEFAULT NULL, UNIQUE INDEX uniq_user_email (email), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE login_challenge ADD CONSTRAINT FK_B797A3A4A76ED395 FOREIGN KEY (user_id) REFERENCES `user` (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE login_challenge DROP FOREIGN KEY FK_B797A3A4A76ED395');
        $this->addSql('DROP TABLE login_challenge');
        $this->addSql('DROP TABLE `user`');
    }
}
