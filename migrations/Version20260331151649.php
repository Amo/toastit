<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260331151649 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE meeting_attendee (id INT AUTO_INCREMENT NOT NULL, created_at DATETIME NOT NULL, meeting_id INT NOT NULL, user_id INT NOT NULL, INDEX IDX_82C1F8B267433D9C (meeting_id), INDEX IDX_82C1F8B2A76ED395 (user_id), UNIQUE INDEX uniq_meeting_attendee (meeting_id, user_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE meeting_attendee ADD CONSTRAINT FK_82C1F8B267433D9C FOREIGN KEY (meeting_id) REFERENCES meeting (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE meeting_attendee ADD CONSTRAINT FK_82C1F8B2A76ED395 FOREIGN KEY (user_id) REFERENCES `user` (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE user ADD first_name VARCHAR(120) DEFAULT NULL, ADD last_name VARCHAR(120) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE meeting_attendee DROP FOREIGN KEY FK_82C1F8B267433D9C');
        $this->addSql('ALTER TABLE meeting_attendee DROP FOREIGN KEY FK_82C1F8B2A76ED395');
        $this->addSql('DROP TABLE meeting_attendee');
        $this->addSql('ALTER TABLE `user` DROP first_name, DROP last_name');
    }
}
