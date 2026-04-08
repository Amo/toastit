<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use Symfony\Component\Uid\Uuid;

final class Version20260408114500 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Persist a UUIDv7 inbound email alias for each user.';
    }

    public function up(Schema $schema): void
    {
        $userTable = $schema->getTable('user');

        if (!$userTable->hasColumn('inbound_email_alias')) {
            $this->addSql('ALTER TABLE user ADD inbound_email_alias VARCHAR(36) DEFAULT NULL');
        }

        $userIds = $this->connection->fetchFirstColumn('SELECT id FROM user WHERE inbound_email_alias IS NULL OR inbound_email_alias = \'\'');

        foreach ($userIds as $userId) {
            $this->addSql(
                'UPDATE user SET inbound_email_alias = ? WHERE id = ?',
                [Uuid::v7()->toRfc4122(), $userId],
                ['string', 'integer'],
            );
        }

        $this->addSql('ALTER TABLE user MODIFY inbound_email_alias VARCHAR(36) NOT NULL');

        if (!$userTable->hasIndex('UNIQ_8D93D649AE1278B3')) {
            $this->addSql('CREATE UNIQUE INDEX UNIQ_8D93D649AE1278B3 ON user (inbound_email_alias)');
        }
    }

    public function down(Schema $schema): void
    {
        $userTable = $schema->getTable('user');

        if ($userTable->hasIndex('UNIQ_8D93D649AE1278B3')) {
            $this->addSql('DROP INDEX UNIQ_8D93D649AE1278B3 ON user');
        }

        if ($userTable->hasColumn('inbound_email_alias')) {
            $this->addSql('ALTER TABLE user DROP inbound_email_alias');
        }
    }
}
