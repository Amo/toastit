<?php

namespace App\Workspace;

use App\Ai\AiPromptTemplateService;
use App\Entity\Workspace;
use App\Entity\User;
use App\Meeting\SessionSummaryUnavailableException;
use App\Meeting\XaiTextService;

final class ToastDraftRefinementService
{
    public function __construct(
        private readonly XaiTextService $xaiText,
        private readonly WorkspaceWorkflowService $workspaceWorkflow,
        private readonly AiPromptTemplateService $promptTemplate,
    ) {
    }

    /**
     * @return array{title: string, description: string, ownerId: ?int, dueOn: ?string}
     */
    public function refine(
        Workspace $workspace,
        string $title,
        ?string $description,
        ?User $requestedBy = null,
        ?string $currentDueOn = null,
    ): array
    {
        $title = trim($title);
        $description = trim((string) $description);
        $currentDueOn = $this->normalizeCurrentDueOn($currentDueOn);

        if ('' === $title && '' === $description) {
            throw new SessionSummaryUnavailableException('missing_input', 'A title or description is required.');
        }

        $systemPrompt = $this->promptTemplate->resolveSystemPrompt(
            'toast_draft_refinement_system',
            '',
            [
                'timezone' => date_default_timezone_get(),
                'today_iso' => (new \DateTimeImmutable('now'))->format(\DateTimeInterface::ATOM),
                'current_due_on' => $currentDueOn ?? 'NONE',
            ],
        );

        if ('' === trim($systemPrompt)) {
            throw new SessionSummaryUnavailableException('invalid_refinement_response', 'No system prompt is configured for toast draft refinement.');
        }

        $userPrompt = $this->promptTemplate->resolveUserPromptTemplate(
            'toast_draft_refinement_system',
            "Requested by:\n{{ requested_by_display_name }}\n\nLanguage rule:\n{{ reword_language_instruction }}\n\nWorkspace name:\n{{ workspace_name }}\n\nWorkspace due-date preference:\n{{ workspace_due_preference }}\n\nWorkspace participants:\n{{ participants_text }}\n\nCurrent title:\n{{ current_title }}\n\nCurrent description:\n{{ current_description }}",
            [
                'requested_by_display_name' => $requestedBy?->getDisplayName() ?? 'UNKNOWN',
                'workspace_name' => $workspace->getName(),
                'workspace_due_preference' => $this->formatWorkspaceDuePreference($workspace->getDefaultDuePreset()),
                'participants_text' => $this->formatParticipants($workspace),
                'current_title' => '' !== $title ? $title : '(empty)',
                'current_description' => '' !== $description ? $description : '(empty)',
                'current_due_on' => $currentDueOn ?? 'NONE',
                'reword_language_instruction' => $this->buildRewordLanguageInstruction($requestedBy),
            ],
        );

        $response = $this->xaiText->generateTextForUser(
            $requestedBy ?? $workspace->getOrganizer(),
            $systemPrompt,
            $userPrompt,
            [
                'source' => 'toast_draft_refinement',
            ],
        );

        return $this->parseResponse($workspace, $response, $currentDueOn);
    }

    /**
     * @return array{title: string, description: string, ownerId: ?int, dueOn: ?string}
     */
    private function parseResponse(Workspace $workspace, string $response, ?string $currentDueOn = null): array
    {
        $normalized = trim(str_replace("\r\n", "\n", $response));

        $payload = json_decode($normalized, true);
        if (is_array($payload) && is_array($payload['result'] ?? null)) {
            $result = $payload['result'];
            $normalized = sprintf(
                "TITLE: %s\nASSIGNEE: %s\nDUE_ON: %s\nDESCRIPTION:\n%s",
                trim((string) ($result['title'] ?? '')),
                trim((string) ($result['assignee'] ?? 'NONE')),
                trim((string) ($result['due_on'] ?? 'NONE')),
                trim((string) ($result['description'] ?? '')),
            );
        }

        if (!preg_match('/^TITLE:\s*(.+?)\nASSIGNEE:\s*(.*?)\nDUE_ON:\s*(.*?)\nDESCRIPTION:\n(.*)$/s', $normalized, $matches)) {
            throw new SessionSummaryUnavailableException('invalid_refinement_response', 'xAI returned an invalid draft refinement response.');
        }

        $title = trim($matches[1]);
        $assignee = trim($matches[2]);
        $dueOn = trim($matches[3]);
        $description = trim($matches[4]);

        if ('' === $title) {
            throw new SessionSummaryUnavailableException('invalid_refinement_response', 'xAI returned an empty title.');
        }

        $owner = null;
        if ('' !== $assignee && 'NONE' !== strtoupper($assignee)) {
          $owner = $this->workspaceWorkflow->findWorkspaceInviteeByDisplayName($workspace, $assignee);
        }

        $resolvedDueOn = $currentDueOn ?? $this->resolveWorkspaceDefaultDueOn($workspace);
        if ('' !== $dueOn && 'NONE' !== strtoupper($dueOn)) {
            try {
                $resolvedDueOn = (new \DateTimeImmutable($dueOn))->format('Y-m-d');
            } catch (\Exception) {
                throw new SessionSummaryUnavailableException('invalid_refinement_response', 'xAI returned an invalid due date.');
            }
        }

        return [
            'title' => $title,
            'description' => $description,
            'ownerId' => $owner?->getId(),
            'dueOn' => $resolvedDueOn,
        ];
    }

    private function formatParticipants(Workspace $workspace): string
    {
        $participants = array_filter(
            $this->workspaceWorkflow->getWorkspaceInvitees($workspace),
            static fn ($participant): bool => null !== $participant->getId(),
        );

        return implode("\n", array_map(
            static fn ($participant): string => sprintf('- %s', $participant->getDisplayName()),
            $participants,
        ));
    }

    private function buildRewordLanguageInstruction(?User $requestedBy): string
    {
        if (!$requestedBy instanceof User) {
            return 'Detect language from current title/description and keep the same language.';
        }

        $preferredLanguage = $requestedBy->getInboundRewordLanguage();
        if (null === $preferredLanguage || '' === trim($preferredLanguage)) {
            return 'Detect language from current title/description and keep the same language.';
        }

        return sprintf(
            'Force output language for title and description to: %s.',
            User::getInboundRewordLanguageLabel($preferredLanguage),
        );
    }

    private function formatWorkspaceDuePreference(string $preset): string
    {
        return match ($preset) {
            Workspace::DEFAULT_DUE_TOMORROW => 'Tomorrow',
            Workspace::DEFAULT_DUE_NEXT_WEEK => 'Next week',
            Workspace::DEFAULT_DUE_IN_2_WEEKS => 'In 2 weeks',
            Workspace::DEFAULT_DUE_NEXT_MONDAY => 'Next Monday',
            Workspace::DEFAULT_DUE_FIRST_MONDAY_NEXT_MONTH => 'First Monday next month',
            default => 'Next week',
        };
    }

    private function resolveWorkspaceDefaultDueOn(Workspace $workspace): string
    {
        $today = new \DateTimeImmutable('today');

        $dueAt = match ($workspace->getDefaultDuePreset()) {
            Workspace::DEFAULT_DUE_TOMORROW => $today->modify('+1 day'),
            Workspace::DEFAULT_DUE_NEXT_WEEK => $today->modify('+7 days'),
            Workspace::DEFAULT_DUE_IN_2_WEEKS => $today->modify('+14 days'),
            Workspace::DEFAULT_DUE_NEXT_MONDAY => $today->modify('next monday'),
            Workspace::DEFAULT_DUE_FIRST_MONDAY_NEXT_MONTH => (new \DateTimeImmutable('first day of next month'))->modify('next monday'),
            default => $today->modify('+7 days'),
        };

        return $dueAt->format('Y-m-d');
    }

    private function normalizeCurrentDueOn(?string $currentDueOn): ?string
    {
        $normalized = trim((string) $currentDueOn);
        if ('' === $normalized) {
            return null;
        }

        try {
            return (new \DateTimeImmutable($normalized))->format('Y-m-d');
        } catch (\Exception) {
            return null;
        }
    }
}
