<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260331150908 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE meeting ADD organizer_id INT DEFAULT NULL');
        $this->addSql('UPDATE meeting m INNER JOIN team t ON m.team_id = t.id SET m.organizer_id = t.organizer_id');
        $this->addSql('ALTER TABLE meeting CHANGE organizer_id organizer_id INT NOT NULL, CHANGE team_id team_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE meeting ADD CONSTRAINT FK_F515E139876C4DDA FOREIGN KEY (organizer_id) REFERENCES `user` (id) ON DELETE CASCADE');
        $this->addSql('CREATE INDEX IDX_F515E139876C4DDA ON meeting (organizer_id)');
        $this->addSql('ALTER TABLE parking_lot_item DROP FOREIGN KEY FK_EDBF6A6C296CD8AE');
        $this->addSql('ALTER TABLE parking_lot_item CHANGE team_id team_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE parking_lot_item ADD CONSTRAINT FK_EDBF6A6C296CD8AE FOREIGN KEY (team_id) REFERENCES team (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE meeting DROP FOREIGN KEY FK_F515E139876C4DDA');
        $this->addSql('DROP INDEX IDX_F515E139876C4DDA ON meeting');
        $this->addSql('ALTER TABLE meeting DROP organizer_id, CHANGE team_id team_id INT NOT NULL');
        $this->addSql('ALTER TABLE parking_lot_item DROP FOREIGN KEY FK_EDBF6A6C296CD8AE');
        $this->addSql('ALTER TABLE parking_lot_item CHANGE team_id team_id INT NOT NULL');
        $this->addSql('ALTER TABLE parking_lot_item ADD CONSTRAINT FK_EDBF6A6C296CD8AE FOREIGN KEY (team_id) REFERENCES team (id) ON DELETE CASCADE');
    }
}
