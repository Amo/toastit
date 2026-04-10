<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260410152000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Unify toast status model and rename legacy team/item tables and foreign keys to workspace/toast.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('RENAME TABLE team TO workspace');
        $this->addSql('RENAME TABLE team_member TO workspace_member');
        $this->addSql('RENAME TABLE parking_lot_item TO toast');

        $this->addSql("UPDATE toast SET status = 'toasted' WHERE discussion_status = 'treated'");
        $this->addSql("UPDATE toast SET status = 'ready' WHERE status = 'open' AND discussion_status = 'ready'");
        $this->addSql("UPDATE toast SET status = 'pending' WHERE status = 'open' AND discussion_status = 'pending'");
        $this->addSql("UPDATE toast SET status = 'discarded' WHERE status = 'vetoed'");
        $this->addSql("UPDATE toast SET status = 'pending' WHERE status NOT IN ('pending', 'ready', 'toasted', 'discarded')");

        $this->addSql('ALTER TABLE toast DROP FOREIGN KEY FK_EDBF6A6C296CD8AE');
        $this->addSql('ALTER TABLE toast DROP FOREIGN KEY FK_EDBF6A6CF9C94C7D');
        $this->addSql('ALTER TABLE workspace_member DROP FOREIGN KEY FK_6FFBDA1296CD8AE');
        $this->addSql('ALTER TABLE vote DROP FOREIGN KEY FK_5A108564126F525E');
        $this->addSql('ALTER TABLE meeting DROP FOREIGN KEY FK_F515E139296CD8AE');

        $this->addSql('ALTER TABLE toast CHANGE team_id workspace_id INT DEFAULT NULL, CHANGE previous_item_id previous_toast_id INT DEFAULT NULL');
        $this->addSql("ALTER TABLE toast CHANGE status status VARCHAR(16) NOT NULL DEFAULT 'pending'");
        $this->addSql('ALTER TABLE toast DROP discussion_status');

        $this->addSql('ALTER TABLE workspace_member CHANGE team_id workspace_id INT NOT NULL');
        $this->addSql('ALTER TABLE workspace_member DROP INDEX uniq_team_member, ADD UNIQUE INDEX uniq_workspace_member (workspace_id, user_id)');

        $this->addSql('ALTER TABLE vote CHANGE item_id toast_id INT NOT NULL');
        $this->addSql('ALTER TABLE vote DROP INDEX uniq_vote_per_user_item, ADD UNIQUE INDEX uniq_vote_per_user_toast (toast_id, user_id)');

        $this->addSql('ALTER TABLE meeting CHANGE team_id workspace_id INT DEFAULT NULL');

        $this->addSql('ALTER TABLE toast ADD CONSTRAINT FK_TOAST_WORKSPACE FOREIGN KEY (workspace_id) REFERENCES workspace (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE toast ADD CONSTRAINT FK_TOAST_PREVIOUS FOREIGN KEY (previous_toast_id) REFERENCES toast (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE workspace_member ADD CONSTRAINT FK_WORKSPACE_MEMBER_WORKSPACE FOREIGN KEY (workspace_id) REFERENCES workspace (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE vote ADD CONSTRAINT FK_VOTE_TOAST FOREIGN KEY (toast_id) REFERENCES toast (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE meeting ADD CONSTRAINT FK_MEETING_WORKSPACE FOREIGN KEY (workspace_id) REFERENCES workspace (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE toast ADD discussion_status VARCHAR(16) NOT NULL DEFAULT \'pending\'');
        $this->addSql("UPDATE toast SET discussion_status = 'treated' WHERE status = 'toasted'");
        $this->addSql("UPDATE toast SET discussion_status = 'ready' WHERE status = 'ready'");
        $this->addSql("UPDATE toast SET discussion_status = 'pending' WHERE status IN ('pending', 'discarded')");
        $this->addSql("UPDATE toast SET status = 'open' WHERE status IN ('pending', 'ready', 'toasted')");
        $this->addSql("ALTER TABLE toast CHANGE status status VARCHAR(16) NOT NULL DEFAULT 'open'");

        $this->addSql('ALTER TABLE toast DROP FOREIGN KEY FK_TOAST_WORKSPACE');
        $this->addSql('ALTER TABLE toast DROP FOREIGN KEY FK_TOAST_PREVIOUS');
        $this->addSql('ALTER TABLE workspace_member DROP FOREIGN KEY FK_WORKSPACE_MEMBER_WORKSPACE');
        $this->addSql('ALTER TABLE vote DROP FOREIGN KEY FK_VOTE_TOAST');
        $this->addSql('ALTER TABLE meeting DROP FOREIGN KEY FK_MEETING_WORKSPACE');

        $this->addSql('ALTER TABLE toast CHANGE workspace_id team_id INT DEFAULT NULL, CHANGE previous_toast_id previous_item_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE workspace_member CHANGE workspace_id team_id INT NOT NULL');
        $this->addSql('ALTER TABLE workspace_member DROP INDEX uniq_workspace_member, ADD UNIQUE INDEX uniq_team_member (team_id, user_id)');
        $this->addSql('ALTER TABLE vote CHANGE toast_id item_id INT NOT NULL');
        $this->addSql('ALTER TABLE vote DROP INDEX uniq_vote_per_user_toast, ADD UNIQUE INDEX uniq_vote_per_user_item (item_id, user_id)');
        $this->addSql('ALTER TABLE meeting CHANGE workspace_id team_id INT DEFAULT NULL');

        $this->addSql('ALTER TABLE toast ADD CONSTRAINT FK_EDBF6A6C296CD8AE FOREIGN KEY (team_id) REFERENCES workspace (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE toast ADD CONSTRAINT FK_EDBF6A6CF9C94C7D FOREIGN KEY (previous_item_id) REFERENCES toast (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE workspace_member ADD CONSTRAINT FK_6FFBDA1296CD8AE FOREIGN KEY (team_id) REFERENCES workspace (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE vote ADD CONSTRAINT FK_5A108564126F525E FOREIGN KEY (item_id) REFERENCES toast (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE meeting ADD CONSTRAINT FK_F515E139296CD8AE FOREIGN KEY (team_id) REFERENCES workspace (id) ON DELETE CASCADE');

        $this->addSql('RENAME TABLE toast TO parking_lot_item');
        $this->addSql('RENAME TABLE workspace_member TO team_member');
        $this->addSql('RENAME TABLE workspace TO team');
    }
}
