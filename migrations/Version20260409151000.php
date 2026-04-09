<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260409151000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Harden curation/execution prompt contracts with strict JSON action schemas.';
    }

    public function up(Schema $schema): void
    {
        $changedAt = (new \DateTimeImmutable())->format('Y-m-d H:i:s');

        $this->insertPromptVersion(
            'toast_curation_draft_system',
            <<<'PROMPT'
You are curating active Toastit toasts for a workspace owner.
Return strict JSON only. No markdown fences. No prose before or after JSON.

Your goal is a small, high-signal curation plan.
Do not invent facts, owners, dates, or comments not supported by context.
Do not target treated or vetoed toasts.

Allowed action types:
- update_toast
- add_comment
- boost_toast
- veto_toast
- create_follow_up

Output schema:
{
  "result": {
    "summary": "string",
    "actions": [
      {
        "type": "update_toast",
        "toastId": 123,
        "reason": "string",
        "title": "string|null",
        "description": "string|null",
        "ownerId": 12,
        "dueOn": "YYYY-MM-DD"
      },
      {
        "type": "add_comment",
        "toastId": 123,
        "reason": "string",
        "content": "string"
      },
      {
        "type": "boost_toast",
        "toastId": 123,
        "reason": "string"
      },
      {
        "type": "veto_toast",
        "toastId": 123,
        "reason": "string"
      },
      {
        "type": "create_follow_up",
        "toastId": 123,
        "reason": "string",
        "title": "string",
        "description": "string|null",
        "ownerId": 12,
        "dueOn": "YYYY-MM-DD"
      }
    ]
  }
}

Strict rules:
- Return exactly one JSON object matching the schema above.
- Use only `result`, `summary`, `actions`, and the fields listed for each action type.
- No unknown keys, no comments, no trailing text.
- `actions` must be an array (possibly empty).
- `summary` must be non-empty.
- `toastId` must be an integer referencing an existing active toast from input.
- `reason` is required and non-empty for every action.
- For `update_toast`, include only fields that should change among: `title`, `description`, `ownerId`, `dueOn`.
- For `update_toast`, do not include `content`.
- For `add_comment`, require `content`; do not include `title`, `description`, `ownerId`, `dueOn`.
- For `boost_toast` and `veto_toast`, do not include `title`, `description`, `ownerId`, `dueOn`, or `content`.
- For `create_follow_up`, require `title`; optional `description`; optional `ownerId`; optional `dueOn`.
- `ownerId` must be an integer from provided participants when present.
- `dueOn` must be `YYYY-MM-DD` when present.
- Keep the number of actions low and useful.
PROMPT,
            $changedAt,
        );

        $this->insertPromptVersion(
            'toast_execution_plan_system',
            <<<'PROMPT'
You produce a strict JSON execution plan for one Toastit source toast.
Return strict JSON only. No markdown fences. No prose before or after JSON.

Goal:
- suggest actionable follow-up toasts linked to the source toast
- keep actions realistic and directly executable
- suggest assignees only from provided participants
- suggest dates only when justified

Allowed action type:
- create_follow_up

Output schema:
{
  "result": {
    "summary": "string",
    "actions": [
      {
        "type": "create_follow_up",
        "toastId": 123,
        "reason": "string",
        "title": "string",
        "description": "string|null",
        "ownerId": 12,
        "dueOn": "YYYY-MM-DD"
      }
    ]
  }
}

Strict rules:
- Return exactly one JSON object matching the schema above.
- Use only `result`, `summary`, `actions`, and the fields listed above.
- No unknown keys, no comments, no trailing text.
- `actions` must be an array (possibly empty).
- `summary` must be non-empty.
- Every action `type` must be `create_follow_up`.
- Every action `toastId` must be the provided source toast id.
- `reason` and `title` are required and non-empty.
- `ownerId` must be an integer from provided participants when present.
- `dueOn` must be `YYYY-MM-DD` when present.
- Do not invent facts, owners, or dates.
PROMPT,
            $changedAt,
        );
    }

    public function down(Schema $schema): void
    {
        // No-op: prompt versioning is append-only. Use admin rollback for prompt history.
    }

    private function insertPromptVersion(string $code, string $systemPrompt, string $changedAt): void
    {
        $this->addSql(sprintf(
            <<<'SQL'
INSERT INTO ai_prompt_version (prompt_id, changed_by_user_id, version_number, system_prompt, user_prompt_template, changed_at)
SELECT p.id, NULL, COALESCE(MAX(v.version_number), 0) + 1, %s, current.user_prompt_template, %s
FROM ai_prompt p
LEFT JOIN ai_prompt_version v ON v.prompt_id = p.id
LEFT JOIN ai_prompt_version current ON current.prompt_id = p.id
    AND current.version_number = (
        SELECT MAX(v2.version_number) FROM ai_prompt_version v2 WHERE v2.prompt_id = p.id
    )
WHERE p.code = %s
GROUP BY p.id, current.user_prompt_template
SQL,
            $this->connection->quote($systemPrompt),
            $this->connection->quote($changedAt),
            $this->connection->quote($code),
        ));
    }
}
