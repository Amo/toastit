<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260409170000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Refine toast draft prompt defaults for missing body quality and requester-first assignee fallback.';
    }

    public function up(Schema $schema): void
    {
        $changedAt = (new \DateTimeImmutable())->format('Y-m-d H:i:s');

        $this->addSql(sprintf(
            'UPDATE ai_prompt SET available_user_variables = %s WHERE code = %s',
            $this->connection->quote(json_encode([
                ['name' => 'requested_by_display_name', 'description' => 'Display name of the user requesting refinement.', 'example' => 'Amaury Leroux de Lens'],
                ['name' => 'participants_text', 'description' => 'Workspace participants as plain text list.', 'example' => "- Amaury Leroux de Lens\n- Alex Martin"],
                ['name' => 'current_title', 'description' => 'Current toast title.', 'example' => 'SES notification'],
                ['name' => 'current_description', 'description' => 'Current toast description/body.', 'example' => '(No email body provided)'],
            ], JSON_THROW_ON_ERROR)),
            $this->connection->quote('toast_draft_refinement_system'),
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
You rewrite Toastit draft toasts.
Output must be strict JSON with this schema:
{
  "result": {
    "title": "string",
    "assignee": "exact participant display name or NONE",
    "due_on": "YYYY-MM-DD or ISO-8601 datetime or NONE",
    "description": "markdown string"
  }
}
Rules:
- preserve intent and language
- keep title short and action-oriented
- never invent owners/dates/facts
- assignee must always be either an exact participant display name or NONE
- if no explicit owner signal exists in the input, default assignee to the "Requested by" participant
- be assertive on due_on when temporal signals exist
- if current_description is "(No email body provided)" or has no actionable detail, do NOT invent context
- in no-body cases, description must stay minimal and factual (one short sentence max), and must not use boilerplate markdown sections (no "## Context", "## Action", etc.)
PROMPT),
            $this->connection->quote(<<<'PROMPT'
Requested by:
{{ requested_by_display_name }}

Workspace participants:
{{ participants_text }}

Current title:
{{ current_title }}

Current description:
{{ current_description }}
PROMPT),
            $this->connection->quote($changedAt),
            $this->connection->quote('toast_draft_refinement_system'),
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
            $this->connection->quote('toast_draft_refinement_system'),
            $this->connection->quote('toast_draft_refinement_system'),
        ));
    }
}

