<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260331181000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add boost state to parking lot items';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE parking_lot_item ADD is_boosted TINYINT(1) NOT NULL DEFAULT 0, ADD boost_rank INT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE parking_lot_item DROP is_boosted, DROP boost_rank');
    }
}
