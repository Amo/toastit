<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260403143000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Persist toast email reply tokens.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE toast_reply_token (id INT AUTO_INCREMENT NOT NULL, user_id INT NOT NULL, toast_id INT NOT NULL, selector VARCHAR(32) NOT NULL, token_hash VARCHAR(255) NOT NULL, action VARCHAR(32) NOT NULL, expires_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', used_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_2C89F2FBA76ED395 (user_id), INDEX IDX_2C89F2FB63C41B11 (toast_id), INDEX idx_toast_reply_token_selector (selector), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE toast_reply_token ADD CONSTRAINT FK_2C89F2FBA76ED395 FOREIGN KEY (user_id) REFERENCES `user` (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE toast_reply_token ADD CONSTRAINT FK_2C89F2FB63C41B11 FOREIGN KEY (toast_id) REFERENCES parking_lot_item (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE toast_reply_token');
    }
}
