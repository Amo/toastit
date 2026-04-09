<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260409193000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Strengthen inbound rewrite prompt contract and requester fallback constraints.';
    }

    public function up(Schema $schema): void
    {
        $changedAt = (new \DateTimeImmutable())->format('Y-m-d H:i:s');

        $this->addSql(sprintf(
            'UPDATE ai_prompt SET available_user_variables = %s, updated_at = %s WHERE code = %s',
            $this->connection->quote(json_encode([
                ['name' => 'sender', 'description' => 'Sender mailbox from inbound email.', 'example' => 'Amazon SES <no-reply@amazonaws.com>'],
                ['name' => 'requested_by_display_name', 'description' => 'Display name of the user receiving the inbound alias.', 'example' => 'Amaury Leroux de Lens'],
                ['name' => 'requested_by_email', 'description' => 'Email of the user receiving the inbound alias.', 'example' => 'amaury@lerouxdelens.com'],
                ['name' => 'email_title', 'description' => 'Inbound email title.', 'example' => 'Amazon SES detected custom MAIL FROM domain'],
                ['name' => 'email_body', 'description' => 'Inbound email body text.', 'example' => '(No email body provided)'],
                ['name' => 'workspace_contexts', 'description' => 'User workspaces and members (name/email) with due presets.', 'example' => 'Workspace: Product...'],
            ], JSON_THROW_ON_ERROR)),
            $this->connection->quote($changedAt),
            $this->connection->quote('inbound_email_rewrite_system'),
        ));

        $this->addSql(sprintf(
            <<<'SQL'
INSERT INTO ai_prompt_version (prompt_id, changed_by_user_id, version_number, system_prompt, user_prompt_template, changed_at)
SELECT p.id, NULL, COALESCE(MAX(v.version_number), 0) + 1, %s, %s, %s
FROM ai_prompt p
LEFT JOIN ai_prompt_version v ON v.prompt_id = p.id
WHERE p.code = %s
GROUP BY p.id
SQL,
            $this->connection->quote(<<<'PROMPT'
You rewrite inbound emails into actionable Toastit tasks.
You must return strict JSON only (no markdown fences, no prose):
{
  "result": {
    "title": "concise action/decision/todo title",
    "description": "structured markdown description",
    "workspace": "exact workspace name from provided list",
    "owner": "exact participant display name or exact participant email from chosen workspace",
    "due_on": "YYYY-MM-DD or ISO-8601 datetime or NONE",
    "confidence": 0,
    "reason": "single concise routing reason"
  }
}

Hard constraints:
- Use email title + email body + sender + provided workspace/member list only.
- Never invent facts, workspaces, participants, dates, or commitments.
- `title` must be concise and action/decision/todo oriented (no email boilerplate, no marketing phrasing).
- `description` must be operational and structured with these exact sections:
  - ## Context
  - ## Required Action
  - ## Ownership & Routing
  - ## Due Date
- In ## Context, summarize only verifiable facts from email.
- In ## Required Action, list concrete next actions as bullets.
- In ## Ownership & Routing, explicitly justify workspace and owner choice in one sentence.
- `workspace` must exactly match one provided workspace name.
- `owner` must exactly match one member (display name or email) from that chosen workspace.
- If no explicit ownership signal exists, `owner` MUST be exactly "Requested by" (display name or email provided in input).
- If no explicit temporal signal exists, `due_on` MUST be `NONE`.
- For machine/system notification emails, still produce an actionable title and required action.
- Never output placeholders, TODO markers, or unresolved variables.
PROMPT),
            $this->connection->quote(<<<'PROMPT'
Requested by: {{ requested_by_display_name }} <{{ requested_by_email }}>
Sender: {{ sender }}
Email title: {{ email_title }}
Email body:
{{ email_body }}

User workspaces:
{{ workspace_contexts }}
PROMPT),
            $this->connection->quote($changedAt),
            $this->connection->quote('inbound_email_rewrite_system'),
        ));
    }

    public function down(Schema $schema): void
    {
        $this->addSql(sprintf(
            <<<'SQL'
DELETE v FROM ai_prompt_version v
INNER JOIN ai_prompt p ON p.id = v.prompt_id
WHERE p.code = %s
  AND v.version_number = (
      SELECT latest.version_number
      FROM (
          SELECT MAX(v2.version_number) AS version_number
          FROM ai_prompt_version v2
          INNER JOIN ai_prompt p2 ON p2.id = v2.prompt_id
          WHERE p2.code = %s
      ) AS latest
  )
SQL,
            $this->connection->quote('inbound_email_rewrite_system'),
            $this->connection->quote('inbound_email_rewrite_system'),
        ));
    }
}
