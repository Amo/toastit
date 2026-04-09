<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260409182000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add unified inbound email rewrite prompt (title, description, workspace, owner, due date).';
    }

    public function up(Schema $schema): void
    {
        $createdAt = (new \DateTimeImmutable())->format('Y-m-d H:i:s');

        $this->addSql(sprintf(
            <<<'SQL'
INSERT INTO ai_prompt (code, label, description, available_variables, available_user_variables, created_at, updated_at)
SELECT %s, %s, %s, %s, %s, %s, %s
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM ai_prompt WHERE code = %s)
SQL,
            $this->connection->quote('inbound_email_rewrite_system'),
            $this->connection->quote('Inbound Email Rewrite'),
            $this->connection->quote('Rewrites inbound emails into Toastit decision/action drafts and structured routing suggestions.'),
            $this->connection->quote(json_encode([], JSON_THROW_ON_ERROR)),
            $this->connection->quote(json_encode([
                ['name' => 'sender', 'description' => 'Sender mailbox from inbound email.', 'example' => 'Amazon SES <no-reply@amazonaws.com>'],
                ['name' => 'email_title', 'description' => 'Inbound email title.', 'example' => 'Amazon SES detected custom MAIL FROM domain'],
                ['name' => 'email_body', 'description' => 'Inbound email body text.', 'example' => '(No email body provided)'],
                ['name' => 'workspace_contexts', 'description' => 'User workspaces and members (name/email) with due presets.', 'example' => 'Workspace: Product...'],
            ], JSON_THROW_ON_ERROR)),
            $this->connection->quote($createdAt),
            $this->connection->quote($createdAt),
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

Rules:
- preserve factual intent from email title/body/sender
- title must be concise and action/decision/todo oriented
- description must be structured and useful for execution
- workspace must be one exact provided workspace name
- owner must be one exact member (name or email) of that chosen workspace
- when owner confidence is weak, pick the most likely responsible member rather than NONE
- set due_on when temporal signal exists; else use NONE
- never invent facts, people, or workspaces
- never output template placeholders
PROMPT),
            $this->connection->quote(<<<'PROMPT'
Sender: {{ sender }}
Email title: {{ email_title }}
Email body:
{{ email_body }}

User workspaces:
{{ workspace_contexts }}
PROMPT),
            $this->connection->quote($createdAt),
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
SQL,
            $this->connection->quote('inbound_email_rewrite_system'),
        ));

        $this->addSql(sprintf(
            'DELETE FROM ai_prompt WHERE code = %s',
            $this->connection->quote('inbound_email_rewrite_system'),
        ));
    }
}

