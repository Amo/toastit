<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260409143000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add structured user variables for toast_curation_draft_system and toast_execution_plan_system prompt templates.';
    }

    public function up(Schema $schema): void
    {
        $changedAt = (new \DateTimeImmutable())->format('Y-m-d H:i:s');

        $this->updateUserVariables('toast_curation_draft_system', [
            ['name' => 'workspace_name', 'description' => 'Current workspace display name.', 'example' => 'Executive Committee'],
            ['name' => 'participants', 'description' => 'Workspace participants list [{id, display_name}].', 'example' => '[{"id":12,"display_name":"Alex Martin"}]'],
            ['name' => 'active_toasts', 'description' => 'Active toasts list with metadata/comments.', 'example' => '[{"toast_id":42,"title":"Clarify launch","status":"open","discussion_status":"pending","author":"Sam","owner_id":12,"owner_name":"Alex Martin","vote_count":2,"is_boosted":false,"due_on":"2026-04-18","description":"...", "comments":[{"author":"Sam","content":"..."}]}]'],
            ['name' => 'context_text', 'description' => 'Legacy preformatted context text kept for compatibility.', 'example' => 'Workspace: ... Active toasts: ...'],
        ]);

        $this->insertVersion(
            'toast_curation_draft_system',
            <<<'PROMPT'
Output strict JSON:
{
  "result": {
    "summary": "string",
    "actions": [ ... ]
  }
}
Use only allowed curation action types.
PROMPT,
            <<<'PROMPT'
Workspace: {{ workspace_name }}

Participants:
{% for participant in participants %}
- {{ participant.id }}: {{ participant.display_name }}
{% else %}
- none
{% endfor %}

Active toasts:
{% for toast in active_toasts %}
- toastId: {{ toast.toast_id }}
  title: {{ toast.title }}
  status: {{ toast.status }} / discussion: {{ toast.discussion_status }}
  author: {{ toast.author }}
  ownerId: {{ toast.owner_id is not null ? toast.owner_id : 'null' }}
  ownerName: {{ toast.owner_name }}
  voteCount: {{ toast.vote_count }}
  isBoosted: {{ toast.is_boosted ? 'true' : 'false' }}
  dueOn: {{ toast.due_on ?? 'null' }}
{% if toast.description %}
  description: {{ toast.description }}
{% endif %}
{% if toast.comments is not empty %}
  comments:
{% for comment in toast.comments %}
    - {{ comment.author }}: {{ comment.content }}
{% endfor %}
{% endif %}
{% else %}
- none
{% endfor %}
PROMPT,
            $changedAt,
        );

        $this->updateUserVariables('toast_execution_plan_system', [
            ['name' => 'workspace_name', 'description' => 'Current workspace display name.', 'example' => 'Executive Committee'],
            ['name' => 'source_toast', 'description' => 'Source toast payload {id,title,current_owner_id,current_due_on,description,decision_notes}.', 'example' => '{"id":42,"title":"Launch scope","current_owner_id":12,"current_due_on":"2026-04-18","description":"...","decision_notes":"..."}'],
            ['name' => 'participants', 'description' => 'Workspace participants list [{id, display_name}].', 'example' => '[{"id":12,"display_name":"Alex Martin"}]'],
            ['name' => 'context_text', 'description' => 'Legacy preformatted context text kept for compatibility.', 'example' => 'Workspace: ...'],
        ]);

        $this->insertVersion(
            'toast_execution_plan_system',
            <<<'PROMPT'
Output strict JSON:
{
  "result": {
    "summary": "string",
    "actions": [ ... ]
  }
}
Use only allowed action type create_follow_up.
PROMPT,
            <<<'PROMPT'
Workspace: {{ workspace_name }}
Source toast id: {{ source_toast.id }}
Source title: {{ source_toast.title }}
Current owner id: {{ source_toast.current_owner_id is not null ? source_toast.current_owner_id : 'null' }}
Current dueOn: {{ source_toast.current_due_on ?? 'null' }}
Description: {{ source_toast.description }}
Decision notes:
{{ source_toast.decision_notes }}

Workspace participants:
{% for participant in participants %}
- {{ participant.id }}: {{ participant.display_name }}
{% else %}
- none
{% endfor %}
PROMPT,
            $changedAt,
        );
    }

    public function down(Schema $schema): void
    {
        // No-op: prompt versioning is append-only. Reverting should be done via admin rollback endpoint.
    }

    /**
     * @param list<array{name: string, description: string, example: string}> $variables
     */
    private function updateUserVariables(string $code, array $variables): void
    {
        $this->addSql(sprintf(
            'UPDATE ai_prompt SET available_user_variables = %s WHERE code = %s',
            $this->connection->quote(json_encode($variables, JSON_THROW_ON_ERROR)),
            $this->connection->quote($code),
        ));
    }

    private function insertVersion(string $code, string $systemPrompt, string $userPromptTemplate, string $changedAt): void
    {
        $this->addSql(sprintf(
            <<<'SQL'
INSERT INTO ai_prompt_version (prompt_id, changed_by_user_id, version_number, system_prompt, user_prompt_template, changed_at)
SELECT p.id, NULL, COALESCE(MAX(v.version_number), 0) + 1, %s, %s, %s
FROM ai_prompt p
LEFT JOIN ai_prompt_version v ON v.prompt_id = p.id
WHERE p.code = %s
GROUP BY p.id
SQL,
            $this->connection->quote($systemPrompt),
            $this->connection->quote($userPromptTemplate),
            $this->connection->quote($changedAt),
            $this->connection->quote($code),
        ));
    }
}
