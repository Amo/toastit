<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260409174000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Strengthen refinement prompt to force requester fallback assignee.';
    }

    public function up(Schema $schema): void
    {
        $changedAt = (new \DateTimeImmutable())->format('Y-m-d H:i:s');

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
    "assignee": "exact participant display name",
    "due_on": "YYYY-MM-DD or ISO-8601 datetime or NONE",
    "description": "markdown string"
  }
}
Hard constraints:
- preserve intent and language
- keep title short and action-oriented
- never invent owners/dates/facts
- title and description must never contain template placeholders (no "{{ ... }}", "{% ... %}", or raw variable names)
- assignee MUST be one exact participant display name copied from the participants list
- NEVER output NONE for assignee
- if no explicit owner signal exists, assignee MUST equal the exact "Requested by" display name
- if current_description is "(No email body provided)" or has no actionable detail, do NOT invent context
- in no-body cases, description must be one short factual sentence max, without boilerplate sections

Validation checklist before output:
1) assignee equals one exact participant display name
2) assignee is not NONE
3) title/description contain no template placeholders
If any check fails, regenerate internally before returning JSON.
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

