<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260331143448 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        $schemaManager = $this->connection->createSchemaManager();
        $table = $schemaManager->introspectTable('parking_lot_item');

        if (!$table->hasColumn('meeting_id')) {
            $this->addSql('ALTER TABLE parking_lot_item ADD meeting_id INT NOT NULL');
        }

        $indexes = $table->getIndexes();
        if (!isset($indexes['IDX_EDBF6A6C67433D9C'])) {
            $this->addSql('CREATE INDEX IDX_EDBF6A6C67433D9C ON parking_lot_item (meeting_id)');
        }

        $foreignKeys = $table->getForeignKeys();
        $hasMeetingForeignKey = false;

        foreach ($foreignKeys as $foreignKey) {
            if (['meeting_id'] === $foreignKey->getLocalColumns()) {
                $hasMeetingForeignKey = true;
                break;
            }
        }

        if (!$hasMeetingForeignKey) {
            $this->addSql('ALTER TABLE parking_lot_item ADD CONSTRAINT FK_EDBF6A6C67433D9C FOREIGN KEY (meeting_id) REFERENCES meeting (id) ON DELETE CASCADE');
        }
    }

    public function down(Schema $schema): void
    {
        $schemaManager = $this->connection->createSchemaManager();
        $table = $schemaManager->introspectTable('parking_lot_item');

        foreach ($table->getForeignKeys() as $foreignKey) {
            if (['meeting_id'] === $foreignKey->getLocalColumns()) {
                $this->addSql(sprintf('ALTER TABLE parking_lot_item DROP FOREIGN KEY %s', $foreignKey->getName()));
                break;
            }
        }

        if ($table->hasIndex('IDX_EDBF6A6C67433D9C')) {
            $this->addSql('DROP INDEX IDX_EDBF6A6C67433D9C ON parking_lot_item');
        }

        if ($table->hasColumn('meeting_id')) {
            $this->addSql('ALTER TABLE parking_lot_item DROP meeting_id');
        }
    }
}
