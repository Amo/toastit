<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260402081500 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Persist per-user workspace list order on workspace memberships.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE team_member ADD display_order INT DEFAULT 0 NOT NULL');
        $this->addSql('
            UPDATE team_member membership
            JOIN (
                SELECT id, ROW_NUMBER() OVER (PARTITION BY user_id ORDER BY created_at ASC, id ASC) AS position
                FROM team_member
            ) ordered_membership ON ordered_membership.id = membership.id
            SET membership.display_order = ordered_membership.position
        ');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE team_member DROP display_order');
    }
}
