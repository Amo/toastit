<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260408150000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create shared application event storage for root analytics.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
CREATE TABLE app_event (
    id INT AUTO_INCREMENT NOT NULL,
    user_id INT DEFAULT NULL,
    kind VARCHAR(64) NOT NULL,
    status VARCHAR(32) DEFAULT NULL,
    source VARCHAR(64) DEFAULT NULL,
    actor_email VARCHAR(180) DEFAULT NULL,
    metadata JSON NOT NULL,
    occurred_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
    INDEX IDX_863D2752A76ED395 (user_id),
    INDEX idx_app_event_kind_occurred_at (kind, occurred_at),
    INDEX idx_app_event_user_occurred_at (user_id, occurred_at),
    PRIMARY KEY(id)
) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
SQL);
        $this->addSql('ALTER TABLE app_event ADD CONSTRAINT FK_863D2752A76ED395 FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE app_event DROP FOREIGN KEY FK_863D2752A76ED395');
        $this->addSql('DROP TABLE app_event');
    }
}
