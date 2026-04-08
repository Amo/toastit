<?php

namespace App\Workspace;

use App\Entity\Toast;
use App\Entity\User;
use App\Meeting\SessionSummaryUnavailableException;
use App\Meeting\XaiTextService;

final class ToastExecutionPlanDraftService
{
    public function __construct(
        private readonly XaiTextService $xaiText,
        private readonly WorkspaceWorkflowService $workspaceWorkflow,
    ) {
    }

    /**
     * @return array{summary: string, actions: list<array<string, mixed>>}
     */
    public function generate(Toast $toast, ?User $requestedBy = null): array
    {
        $workspace = $toast->getWorkspace();
        $decisionNotes = trim((string) $toast->getDiscussionNotes());

        if ('' === $decisionNotes) {
            throw new SessionSummaryUnavailableException('missing_decision_notes', 'Decision notes must be saved before generating an execution plan.');
        }

        $response = $this->xaiText->generateText(
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
            $this->buildContext($toast),
            [
                'source' => 'toast_execution_plan',
                'userId' => $requestedBy?->getId(),
            ],
        );

        return $this->parseResponse($response, $toast->getId() ?? 0);
    }

    private function buildContext(Toast $toast): string
    {
        $workspace = $toast->getWorkspace();
        $participants = array_filter(
            $this->workspaceWorkflow->getWorkspaceInvitees($workspace),
            static fn ($participant): bool => null !== $participant->getId(),
        );

        $participantLines = array_map(
            static fn ($participant): string => sprintf('- %d: %s', $participant->getId(), $participant->getDisplayName()),
            $participants,
        );

        return implode("\n", [
            sprintf('Workspace: %s', $workspace->getName()),
            sprintf('Source toast id: %d', $toast->getId()),
            sprintf('Source title: %s', $toast->getTitle()),
            sprintf('Current owner id: %s', null !== $toast->getOwner()?->getId() ? (string) $toast->getOwner()->getId() : 'null'),
            sprintf('Current dueOn: %s', $toast->getDueAt()?->format('Y-m-d') ?? 'null'),
            sprintf('Description: %s', trim((string) $toast->getDescription()) ?: '(empty)'),
            'Decision notes:',
            trim((string) $toast->getDiscussionNotes()),
            '',
            'Workspace participants:',
            ...$participantLines,
        ]);
    }

    /**
     * @return array{summary: string, actions: list<array<string, mixed>>}
     */
    private function parseResponse(string $response, int $toastId): array
    {
        $normalized = trim($response);
        $payload = json_decode($normalized, true);

        if (!is_array($payload) || !is_array($payload['actions'] ?? null)) {
            throw new SessionSummaryUnavailableException('invalid_execution_plan', 'xAI returned an invalid execution plan.');
        }

        $actions = array_values(array_filter(
            $payload['actions'],
            static fn ($action): bool => is_array($action) && (($action['type'] ?? null) === 'create_follow_up'),
        ));

        foreach ($actions as &$action) {
            $action['toastId'] = $toastId;
        }

        return [
            'summary' => trim((string) ($payload['summary'] ?? '')) ?: 'Execution plan generated from decision notes.',
            'actions' => $actions,
        ];
    }
}
