<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260408114000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Repair invalid local user.roles values before inbound alias migration.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
UPDATE user
SET roles = '[]'
WHERE roles IS NULL
   OR TRIM(roles) = ''
   OR JSON_VALID(roles) = 0
SQL);
    }

    public function down(Schema $schema): void
    {
        // No-op: this migration only repairs invalid local data.
    }
}
