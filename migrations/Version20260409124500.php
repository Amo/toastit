<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260409124500 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add user prompt templates/variables and seed v2 normalized output contracts.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE ai_prompt ADD available_user_variables JSON DEFAULT NULL");
        $this->addSql("ALTER TABLE ai_prompt_version ADD user_prompt_template LONGTEXT DEFAULT NULL");

        $changedAt = (new \DateTimeImmutable())->format('Y-m-d H:i:s');

        $this->updateUserVariables('toast_draft_refinement_system', [
            ['name' => 'participants_text', 'description' => 'Workspace participants as plain text list.', 'example' => "- 12: Alex\n- 18: Sam"],
            ['name' => 'current_title', 'description' => 'Current toast title.', 'example' => 'Launch follow-up'],
            ['name' => 'current_description', 'description' => 'Current toast description/body.', 'example' => 'Need decision by saturday.'],
        ]);
        $this->insertVersion(
            'toast_draft_refinement_system',
            <<<'PROMPT'
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
- be assertive on due_on when temporal signals exist
- never invent owners/dates/facts
PROMPT,
            <<<'PROMPT'
Workspace participants:
{{ participants_text }}

Current title:
{{ current_title }}

Current description:
{{ current_description }}
PROMPT,
            $changedAt,
        );

        $this->updateUserVariables('workspace_suggestion_system', [
            ['name' => 'toast_title', 'description' => 'Inbound toast title.', 'example' => 'Client networking saturday'],
            ['name' => 'toast_description', 'description' => 'Inbound toast description.', 'example' => 'Professional contacts from my network.'],
            ['name' => 'workspace_list_text', 'description' => 'Available non-inbox workspace names.', 'example' => "- Professional Network\n- Contract End"],
        ]);
        $this->insertVersion(
            'workspace_suggestion_system',
            <<<'PROMPT'
Choose the best Toastit workspace only when highly certain.
Output must be strict JSON:
{
  "result": {
    "workspace": "exact workspace name or NONE",
    "confidence": 0,
    "reason": "single concise sentence"
  }
}
Rules:
- be conservative; avoid false positives
- if ambiguous, return workspace=NONE
PROMPT,
            <<<'PROMPT'
Toast title: {{ toast_title }}
Toast description: {{ toast_description }}
Available workspaces:
{{ workspace_list_text }}
PROMPT,
            $changedAt,
        );

        $this->updateUserVariables('todo_digest_system', [
            ['name' => 'user_display_name', 'description' => 'Current user display name.', 'example' => 'Alex Martin'],
            ['name' => 'user_email', 'description' => 'Current user email.', 'example' => 'alex@example.com'],
            ['name' => 'today_date', 'description' => 'Current date.', 'example' => '2026-04-09'],
            ['name' => 'assigned_actions_text', 'description' => 'Assigned actions block.', 'example' => "- id: 12\n  title: ..."],
        ]);
        $this->insertVersion(
            'todo_digest_system',
            <<<'PROMPT'
Output must be strict JSON:
{
  "result": {
    "markdown": "markdown string with title ## Top 10 actions and up to 10 bullet lines"
  }
}
Rules:
- concise and actionable
- one line per action bullet
PROMPT,
            <<<'PROMPT'
User: {{ user_display_name }}
Email: {{ user_email }}
Today: {{ today_date }}
Assigned active actions:
{{ assigned_actions_text }}
PROMPT,
            $changedAt,
        );

        $this->updateUserVariables('toast_execution_plan_system', [
            ['name' => 'context_text', 'description' => 'Execution plan context text.', 'example' => 'Workspace: ...'],
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
            "{{ context_text }}",
            $changedAt,
        );

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

        $this->updateUserVariables('session_summary_system', [
            ['name' => 'summary_context', 'description' => 'Session context text generated from workspace/session data.', 'example' => 'Workspace: ... Session: ...'],
            ['name' => 'requested_by', 'description' => 'Display name of requesting user.', 'example' => 'Owner User'],
        ]);
        $this->insertVersion(
            'session_summary_system',
            <<<'PROMPT'
Output strict JSON:
{
  "result": {
    "markdown": "concise actionable recap markdown"
  }
}
Stay grounded in provided data.
PROMPT,
            <<<'PROMPT'
{{ summary_context }}

Requested by: {{ requested_by }}
PROMPT,
            $changedAt,
        );

        $this->addSql("UPDATE ai_prompt SET available_user_variables = '[]' WHERE available_user_variables IS NULL");
        $this->addSql("UPDATE ai_prompt_version SET user_prompt_template = '' WHERE user_prompt_template IS NULL");
        $this->addSql("ALTER TABLE ai_prompt MODIFY available_user_variables JSON NOT NULL");
        $this->addSql("ALTER TABLE ai_prompt_version MODIFY user_prompt_template LONGTEXT NOT NULL");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE ai_prompt DROP available_user_variables');
        $this->addSql('ALTER TABLE ai_prompt_version DROP user_prompt_template');
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
