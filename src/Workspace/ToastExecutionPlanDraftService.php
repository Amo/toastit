<?php

namespace App\Workspace;

use App\Ai\AiPromptTemplateService;
use App\Entity\Toast;
use App\Entity\User;
use App\Meeting\SessionSummaryUnavailableException;
use App\Meeting\XaiTextService;

final class ToastExecutionPlanDraftService
{
    public function __construct(
        private readonly XaiTextService $xaiText,
        private readonly WorkspaceWorkflowService $workspaceWorkflow,
        private readonly AiPromptTemplateService $promptTemplate,
    ) {
    }

    /**
     * @return array{summary: string, actions: list<array<string, mixed>>}
     */
    public function generate(Toast $toast, ?User $requestedBy = null, ?string $decisionNotesOverride = null): array
    {
        $workspace = $toast->getWorkspace();
        $decisionNotes = trim((string) ($decisionNotesOverride ?? $toast->getDiscussionNotes()));

        if ('' === $decisionNotes) {
            throw new SessionSummaryUnavailableException('missing_decision_notes', 'Decision notes are required before generating an execution plan.');
        }

        $systemPrompt = $this->promptTemplate->resolveSystemPrompt('toast_execution_plan_system', '');
        if ('' === trim($systemPrompt)) {
            throw new SessionSummaryUnavailableException('invalid_execution_plan', 'No execution-plan system prompt is configured.');
        }

        $promptVariables = $this->buildPromptVariables($toast, $decisionNotes);
        $userPrompt = $this->promptTemplate->resolveUserPromptTemplate(
            'toast_execution_plan_system',
            '{{ context_text }}',
            $promptVariables,
        );

        $response = $this->xaiText->generateTextForUser(
            $requestedBy ?? $workspace->getOrganizer(),
            $systemPrompt,
            $userPrompt,
            [
                'source' => 'toast_execution_plan',
            ],
        );

        return $this->parseResponse($response, $toast->getId() ?? 0);
    }

    private function buildPromptVariables(Toast $toast, string $decisionNotes): array
    {
        $workspace = $toast->getWorkspace();
        $participants = array_filter(
            $this->workspaceWorkflow->getWorkspaceInvitees($workspace),
            static fn ($participant): bool => null !== $participant->getId(),
        );

        $participantPayload = array_map(
            static fn ($participant): array => [
                'id' => (int) $participant->getId(),
                'display_name' => $participant->getDisplayName(),
            ],
            $participants,
        );

        $legacyContextText = implode("\n", [
            sprintf('Workspace: %s', $workspace->getName()),
            sprintf('Source toast id: %d', $toast->getId()),
            sprintf('Source title: %s', $toast->getTitle()),
            sprintf('Current owner id: %s', null !== $toast->getOwner()?->getId() ? (string) $toast->getOwner()->getId() : 'null'),
            sprintf('Current dueOn: %s', $toast->getDueAt()?->format('Y-m-d') ?? 'null'),
            sprintf('Description: %s', trim((string) $toast->getDescription()) ?: '(empty)'),
            'Decision notes:',
            $decisionNotes,
            '',
            'Workspace participants:',
            ...array_map(
                static fn (array $participant): string => sprintf('- %d: %s', $participant['id'], $participant['display_name']),
                $participantPayload,
            ),
        ]);

        return [
            'workspace_name' => $workspace->getName(),
            'source_toast' => [
                'id' => (int) ($toast->getId() ?? 0),
                'title' => $toast->getTitle(),
                'current_owner_id' => $toast->getOwner()?->getId(),
                'current_due_on' => $toast->getDueAt()?->format('Y-m-d'),
                'description' => trim((string) $toast->getDescription()) ?: '(empty)',
                'decision_notes' => $decisionNotes,
            ],
            'participants' => array_values($participantPayload),
            'context_text' => $legacyContextText,
        ];
    }

    /**
     * @return array{summary: string, actions: list<array<string, mixed>>}
     */
    private function parseResponse(string $response, int $toastId): array
    {
        $normalized = trim($response);
        $payload = json_decode($normalized, true);
        if (is_array($payload) && is_array($payload['result'] ?? null)) {
            $payload = $payload['result'];
        }

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
