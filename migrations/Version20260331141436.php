<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260331141436 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE meeting (id INT AUTO_INCREMENT NOT NULL, title VARCHAR(180) NOT NULL, scheduled_at DATETIME NOT NULL, is_recurring TINYINT NOT NULL, recurrence VARCHAR(32) DEFAULT NULL, video_link VARCHAR(255) DEFAULT NULL, created_at DATETIME NOT NULL, team_id INT NOT NULL, INDEX IDX_F515E139296CD8AE (team_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE parking_lot_item (id INT AUTO_INCREMENT NOT NULL, title VARCHAR(180) NOT NULL, description LONGTEXT DEFAULT NULL, status VARCHAR(16) NOT NULL, created_at DATETIME NOT NULL, team_id INT NOT NULL, author_id INT NOT NULL, INDEX IDX_EDBF6A6C296CD8AE (team_id), INDEX IDX_EDBF6A6CF675F31B (author_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE team (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(160) NOT NULL, created_at DATETIME NOT NULL, organizer_id INT NOT NULL, INDEX IDX_C4E0A61F876C4DDA (organizer_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE team_member (id INT AUTO_INCREMENT NOT NULL, created_at DATETIME NOT NULL, team_id INT NOT NULL, user_id INT NOT NULL, INDEX IDX_6FFBDA1296CD8AE (team_id), INDEX IDX_6FFBDA1A76ED395 (user_id), UNIQUE INDEX uniq_team_member (team_id, user_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE vote (id INT AUTO_INCREMENT NOT NULL, created_at DATETIME NOT NULL, item_id INT NOT NULL, user_id INT NOT NULL, INDEX IDX_5A108564126F525E (item_id), INDEX IDX_5A108564A76ED395 (user_id), UNIQUE INDEX uniq_vote_per_user_item (item_id, user_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE meeting ADD CONSTRAINT FK_F515E139296CD8AE FOREIGN KEY (team_id) REFERENCES team (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE parking_lot_item ADD CONSTRAINT FK_EDBF6A6C296CD8AE FOREIGN KEY (team_id) REFERENCES team (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE parking_lot_item ADD CONSTRAINT FK_EDBF6A6CF675F31B FOREIGN KEY (author_id) REFERENCES `user` (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE team ADD CONSTRAINT FK_C4E0A61F876C4DDA FOREIGN KEY (organizer_id) REFERENCES `user` (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE team_member ADD CONSTRAINT FK_6FFBDA1296CD8AE FOREIGN KEY (team_id) REFERENCES team (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE team_member ADD CONSTRAINT FK_6FFBDA1A76ED395 FOREIGN KEY (user_id) REFERENCES `user` (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE vote ADD CONSTRAINT FK_5A108564126F525E FOREIGN KEY (item_id) REFERENCES parking_lot_item (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE vote ADD CONSTRAINT FK_5A108564A76ED395 FOREIGN KEY (user_id) REFERENCES `user` (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE user CHANGE roles roles JSON DEFAULT \'[]\' NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE meeting DROP FOREIGN KEY FK_F515E139296CD8AE');
        $this->addSql('ALTER TABLE parking_lot_item DROP FOREIGN KEY FK_EDBF6A6C296CD8AE');
        $this->addSql('ALTER TABLE parking_lot_item DROP FOREIGN KEY FK_EDBF6A6CF675F31B');
        $this->addSql('ALTER TABLE team DROP FOREIGN KEY FK_C4E0A61F876C4DDA');
        $this->addSql('ALTER TABLE team_member DROP FOREIGN KEY FK_6FFBDA1296CD8AE');
        $this->addSql('ALTER TABLE team_member DROP FOREIGN KEY FK_6FFBDA1A76ED395');
        $this->addSql('ALTER TABLE vote DROP FOREIGN KEY FK_5A108564126F525E');
        $this->addSql('ALTER TABLE vote DROP FOREIGN KEY FK_5A108564A76ED395');
        $this->addSql('DROP TABLE meeting');
        $this->addSql('DROP TABLE parking_lot_item');
        $this->addSql('DROP TABLE team');
        $this->addSql('DROP TABLE team_member');
        $this->addSql('DROP TABLE vote');
        $this->addSql('ALTER TABLE `user` CHANGE roles roles JSON NOT NULL');
    }
}
