<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260417114500 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add persistent AI refinement pending flag on toasts.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE toast ADD ai_refinement_pending TINYINT(1) NOT NULL DEFAULT 0');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE toast DROP ai_refinement_pending');
    }
}
