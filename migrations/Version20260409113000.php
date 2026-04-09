<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260409113000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create AI prompt registry with versioning and seed existing xAI system prompts.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
CREATE TABLE ai_prompt (
    id INT AUTO_INCREMENT NOT NULL,
    code VARCHAR(120) NOT NULL,
    label VARCHAR(180) NOT NULL,
    description LONGTEXT DEFAULT NULL,
    available_variables JSON NOT NULL,
    created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
    updated_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
    UNIQUE INDEX uniq_ai_prompt_code (code),
    PRIMARY KEY(id)
) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
SQL);

        $this->addSql(<<<'SQL'
CREATE TABLE ai_prompt_version (
    id INT AUTO_INCREMENT NOT NULL,
    prompt_id INT NOT NULL,
    changed_by_user_id INT DEFAULT NULL,
    version_number INT NOT NULL,
    system_prompt LONGTEXT NOT NULL,
    changed_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
    INDEX IDX_1132EC8A5BA6AF4C (prompt_id),
    INDEX IDX_1132EC8A8C03F15C (changed_by_user_id),
    UNIQUE INDEX uniq_ai_prompt_version_number (prompt_id, version_number),
    PRIMARY KEY(id)
) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
SQL);

        $this->addSql('ALTER TABLE ai_prompt_version ADD CONSTRAINT FK_1132EC8A5BA6AF4C FOREIGN KEY (prompt_id) REFERENCES ai_prompt (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE ai_prompt_version ADD CONSTRAINT FK_1132EC8A8C03F15C FOREIGN KEY (changed_by_user_id) REFERENCES user (id) ON DELETE SET NULL');

        $seedAt = (new \DateTimeImmutable())->format('Y-m-d H:i:s');

        $this->seedPrompt(
            'toast_draft_refinement_system',
            'Toast Draft Refinement',
            'Refines inbound toast title/description and suggests owner/due date.',
            [
                ['name' => 'timezone', 'description' => 'Reference timezone string for relative date resolution.', 'example' => 'Europe/Paris'],
                ['name' => 'today_iso', 'description' => 'Current datetime in ISO 8601 format.', 'example' => '2026-04-09T10:45:00+02:00'],
            ],
            <<<'PROMPT'
You rewrite Toastit draft toasts.
Your job is to improve clarity for fast team decision-making.
Constraints:
- Keep the original meaning and intent.
- Return the result in the same language as the input.
- Produce a very short, action-driven title.
- Prefer an imperative or decision-oriented phrasing when relevant.
- The title should usually stay within 3 to 6 words.
- Do not pack context, sub-points, examples, or long qualifiers into the title.
- If the original title contains useful detail, move that detail into the structured description instead of keeping it in the title.
- Remove vague phrasing, buzzwords, and fuzzy umbrella terms.
- Produce a structured description in Markdown.
- Use the description to capture the important context, scope, constraints, options, and decision framing that do not fit in the title.
- The description should be concise, scannable, action-oriented, and end with a clear call to action that helps decision-making.
- You may suggest an assignee if one participant is explicitly the best fit for the next step.
- You must be assertive on due dates when the source contains any temporal signal.
- Temporal signal examples: exact date, exact datetime, weekday mention, relative phrase ("today", "tomorrow", "this Saturday", "ce samedi", "vendredi prochain"), deadline wording ("before", "d'ici", "au plus tard"), or meeting timing context.
- If temporal signals are present, do not output NONE for due date.
- Resolve relative expressions against the provided "Today" and "Reference timezone".
- If the source includes an exact datetime, keep that precision mentally and output the corresponding calendar date for due date.
- Do not invent facts, owners, dates, budgets, or decisions that are not present in the source.
- Output must follow this exact format:
TITLE: <single line>
ASSIGNEE: <exact participant display name or NONE>
DUE_ON: <YYYY-MM-DD or ISO-8601 datetime or NONE>
DESCRIPTION:
<markdown description>
PROMPT,
            $seedAt,
        );

        $this->seedPrompt(
            'workspace_suggestion_system',
            'Workspace Suggestion',
            'Suggests target workspace for inbound toast when highly confident.',
            [],
            <<<'PROMPT'
You choose the best Toastit workspace for a newly created toast.
Return one existing workspace name from the list only when highly certain, otherwise return NONE.
Be conservative: if context is ambiguous, mixed personal/professional, or plausibly matches multiple workspaces, return NONE.
Prefer precision over recall: avoid false positives.
Output must follow this exact format:
WORKSPACE: <exact workspace name or NONE>
CONFIDENCE: <integer 0-100>
REASON: <single concise sentence>
PROMPT,
            $seedAt,
        );

        $this->seedPrompt(
            'todo_digest_system',
            'Todo Digest',
            'Builds top actionable todo digest lines for assignee.',
            [],
            <<<'PROMPT'
You are helping a Toastit user decide what to do next.
Review only the active actions assigned to that user.
Return a markdown answer titled "## Top 10 actions".
List at most 10 actions, in priority order.
Use exactly one concise line per action in this format:
- [<id>] <title>, <date>, <assignee>
Rules:
- <id> is the task id as an integer
- <date> is YYYY-MM-DD or "none"
- <assignee> is the assignee display name
- Do not include workspace, rationale, prose, or extra sections
- Do not add any text before or after the list
PROMPT,
            $seedAt,
        );

        $this->seedPrompt(
            'toast_execution_plan_system',
            'Toast Execution Plan',
            'Generates strict JSON follow-up execution plan for a toast.',
            [],
            <<<'PROMPT'
You produce a strict JSON execution plan for a single Toastit toast discussed in toasting mode.
Return JSON only. No markdown fences. No prose before or after JSON.

Goal:
- suggest actionable follow-up toasts linked to the current source toast
- each follow-up must be precise, action-oriented, and independently executable
- suggest assignees only from the provided participants
- suggest dates when the effort, urgency, and dependencies justify them

Allowed action type:
- "create_follow_up"

JSON schema:
{
  "summary": "short explanation of the execution plan",
  "actions": [
    {
      "type": "create_follow_up",
      "toastId": 123,
      "reason": "why this follow-up should exist",
      "title": "short action-driven follow-up title",
      "description": "markdown description, precise and actionable",
      "ownerId": 12 or null,
      "dueOn": "YYYY-MM-DD" or null
    }
  ]
}

Rules:
- every action must target the provided source toast id
- keep the plan tight and realistic
- no invented facts, owners, or dates outside the provided context
- follow-up titles should be concise and action-driven
- descriptions should clarify expected outcome, constraints, and next move
PROMPT,
            $seedAt,
        );

        $this->seedPrompt(
            'toast_curation_draft_system',
            'Toast Curation Draft',
            'Generates strict JSON curation plan over active toasts.',
            [],
            <<<'PROMPT'
You are curating active Toastit toasts for a workspace owner.
You must return strict JSON only. No markdown fences. No prose before or after JSON.

Your job is to propose a small, high-signal curation plan for the active toasts.
Prefer precise, minimal actions that reduce ambiguity and prepare decisions.
Do not invent business facts, owners, dates, or comments that are not strongly supported by the provided data.
Do not modify already treated or vetoed toasts.

Allowed action types:
- "update_toast": refine title/description/ownerId/dueOn for an existing active toast
- "add_comment": add a concise comment to an existing active toast
- "boost_toast": boost an existing active toast
- "veto_toast": veto an existing active toast
- "create_follow_up": create a new follow-up toast from an existing active toast

JSON schema:
{
  "summary": "short explanation of the proposed curation plan",
  "actions": [
    {
      "type": "update_toast" | "add_comment" | "boost_toast" | "veto_toast" | "create_follow_up",
      "toastId": 123,
      "reason": "why this action is useful",
      "title": "required for update_toast/create_follow_up when relevant",
      "description": "optional markdown description",
      "ownerId": 12 or null,
      "dueOn": "YYYY-MM-DD" or null,
      "content": "required for add_comment"
    }
  ]
}

Rules:
- Keep the number of actions low and useful.
- For "update_toast", include only fields that should be changed.
- For "create_follow_up", use toastId as the source toast id.
- Use ownerId only if the owner is explicitly known from the provided workspace participants.
- Use dueOn only if a date is clearly justified.
- Every action must include a non-empty "reason".
PROMPT,
            $seedAt,
        );

        $this->seedPrompt(
            'session_summary_system',
            'Session Summary',
            'Generates operational recap for toasting sessions.',
            [],
            <<<'PROMPT'
You produce operational meeting recaps for Toastit.
Your output must stay grounded in the provided workspace/session data.
Do not invent decisions, owners, dates, or follow-ups.
When information is ambiguous or missing, call it out explicitly.
Keep the recap concise, actionable, and suitable for sharing with the team.
PROMPT,
            $seedAt,
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE ai_prompt_version DROP FOREIGN KEY FK_1132EC8A5BA6AF4C');
        $this->addSql('ALTER TABLE ai_prompt_version DROP FOREIGN KEY FK_1132EC8A8C03F15C');
        $this->addSql('DROP TABLE ai_prompt_version');
        $this->addSql('DROP TABLE ai_prompt');
    }

    /**
     * @param list<array{name: string, description: string, example: string}> $availableVariables
     */
    private function seedPrompt(string $code, string $label, string $description, array $availableVariables, string $systemPrompt, string $seedAt): void
    {
        $this->addSql(sprintf(
            'INSERT INTO ai_prompt (code, label, description, available_variables, created_at, updated_at) VALUES (%s, %s, %s, %s, %s, %s)',
            $this->connection->quote($code),
            $this->connection->quote($label),
            $this->connection->quote($description),
            $this->connection->quote(json_encode($availableVariables, JSON_THROW_ON_ERROR)),
            $this->connection->quote($seedAt),
            $this->connection->quote($seedAt),
        ));

        $this->addSql(sprintf(
            'INSERT INTO ai_prompt_version (prompt_id, changed_by_user_id, version_number, system_prompt, changed_at) SELECT id, NULL, 1, %s, %s FROM ai_prompt WHERE code = %s',
            $this->connection->quote($systemPrompt),
            $this->connection->quote($seedAt),
            $this->connection->quote($code),
        ));
    }
}
