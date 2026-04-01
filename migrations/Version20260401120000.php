<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260401120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add comments on active toasts.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE toast_comment (id INT AUTO_INCREMENT NOT NULL, toast_id INT NOT NULL, author_id INT NOT NULL, content LONGTEXT NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_3552B2A81CFB54B2 (toast_id), INDEX IDX_3552B2A8F675F31B (author_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE toast_comment ADD CONSTRAINT FK_3552B2A81CFB54B2 FOREIGN KEY (toast_id) REFERENCES parking_lot_item (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE toast_comment ADD CONSTRAINT FK_3552B2A8F675F31B FOREIGN KEY (author_id) REFERENCES `user` (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE toast_comment');
    }
}
