<?php

namespace App\Workspace;

use App\Ai\AiPromptTemplateService;
use App\Entity\Toast;
use App\Entity\User;
use App\Entity\Workspace;
use App\Meeting\MeetingAgendaBuilder;
use App\Meeting\SessionSummaryUnavailableException;
use App\Meeting\XaiTextService;

final class ToastCurationDraftService
{
    public function __construct(
        private readonly XaiTextService $xaiText,
        private readonly MeetingAgendaBuilder $meetingAgendaBuilder,
        private readonly WorkspaceWorkflowService $workspaceWorkflow,
        private readonly AiPromptTemplateService $promptTemplate,
    ) {
    }

    /**
     * @return array{
     *   summary: string,
     *   actions: list<array<string, mixed>>,
     *   activeToastCount: int
     * }
     */
    public function generateDraft(Workspace $workspace, ?User $requestedBy = null): array
    {
        $agenda = $this->meetingAgendaBuilder->build($workspace);
        $activeItems = $agenda->activeItems;

        if ([] === $activeItems) {
            return [
                'summary' => 'No active toasts require curation.',
                'actions' => [],
                'activeToastCount' => 0,
            ];
        }

        $systemPrompt = $this->promptTemplate->resolveSystemPrompt('toast_curation_draft_system', '');
        if ('' === trim($systemPrompt)) {
            throw new SessionSummaryUnavailableException('invalid_curation_draft', 'No curation system prompt is configured.');
        }

        $promptVariables = $this->buildPromptVariables($workspace, $activeItems);
        $userPrompt = $this->promptTemplate->resolveUserPromptTemplate(
            'toast_curation_draft_system',
            '{{ context_text }}',
            $promptVariables,
        );

        $response = $this->xaiText->generateTextForUser(
            $requestedBy ?? $workspace->getOrganizer(),
            $systemPrompt,
            $userPrompt,
            [
                'source' => 'toast_curation_draft',
            ],
        );

        return $this->parseDraftResponse($response, count($activeItems));
    }

    /**
     * @param list<Toast> $activeItems
     */
    private function buildPromptVariables(Workspace $workspace, array $activeItems): array
    {
        $participants = $this->workspaceWorkflow->getWorkspaceInvitees($workspace);
        $promptParticipants = array_values(array_map(
            static fn ($participant): array => [
                'id' => (int) $participant->getId(),
                'display_name' => $participant->getDisplayName(),
            ],
            array_filter($participants, static fn ($participant): bool => null !== $participant->getId()),
        ));

        $promptToasts = array_map(function (Toast $toast): array {
            $comments = [];
            foreach ($toast->getComments() as $comment) {
                $comments[] = [
                    'author' => $comment->getAuthor()->getDisplayName(),
                    'content' => $this->normalizeText($comment->getContent()),
                ];
            }

            return [
                'toast_id' => (int) $toast->getId(),
                'title' => $toast->getTitle(),
                'status' => $toast->getStatus(),
                'author' => $toast->getAuthor()->getDisplayName(),
                'owner_id' => $toast->getOwner()?->getId(),
                'owner_name' => $toast->getOwner()?->getDisplayName() ?? 'unassigned',
                'vote_count' => $toast->getVoteCount(),
                'is_boosted' => $toast->isBoosted(),
                'due_on' => $toast->getDueAt()?->format('Y-m-d'),
                'description' => $this->normalizeOptionalText($toast->getDescription()),
                'comments' => $comments,
            ];
        }, $activeItems);

        return [
            'workspace_name' => $workspace->getName(),
            'participants' => $promptParticipants,
            'active_toasts' => $promptToasts,
            'context_text' => $this->buildLegacyContextText($workspace->getName(), $promptParticipants, $promptToasts),
        ];
    }

    /**
     * @param list<array{id: int, display_name: string}> $participants
     * @param list<array{
     *   toast_id: int,
     *   title: string,
     *   status: string,
     *   author: string,
     *   owner_id: int|null,
     *   owner_name: string,
     *   vote_count: int,
     *   is_boosted: bool,
     *   due_on: string|null,
     *   description: string|null,
     *   comments: list<array{author: string, content: string}>
     * }> $toasts
     */
    private function buildLegacyContextText(string $workspaceName, array $participants, array $toasts): string
    {
        $participantLines = array_map(
            static fn (array $participant): string => sprintf('- %d: %s', $participant['id'], $participant['display_name']),
            $participants,
        );

        $toastLines = array_map(function (array $toast): string {
            $lines = [
                sprintf('- toastId: %d', $toast['toast_id']),
                sprintf('  title: %s', $toast['title']),
                sprintf('  status: %s', $toast['status']),
                sprintf('  author: %s', $toast['author']),
                sprintf('  ownerId: %s', null !== $toast['owner_id'] ? (string) $toast['owner_id'] : 'null'),
                sprintf('  ownerName: %s', $toast['owner_name']),
                sprintf('  voteCount: %d', $toast['vote_count']),
                sprintf('  isBoosted: %s', $toast['is_boosted'] ? 'true' : 'false'),
            ];

            if (null !== $toast['due_on']) {
                $lines[] = sprintf('  dueOn: %s', $toast['due_on']);
            }

            if (null !== $toast['description']) {
                $lines[] = sprintf('  description: %s', $toast['description']);
            }

            if ([] !== $toast['comments']) {
                $lines[] = '  comments:';
                foreach ($toast['comments'] as $comment) {
                    $lines[] = sprintf('    - %s: %s', $comment['author'], $comment['content']);
                }
            }

            return implode("\n", $lines);
        }, $toasts);

        return implode("\n", [
            sprintf('Workspace: %s', $workspaceName),
            '',
            'Participants:',
            ...$participantLines,
            '',
            'Active toasts:',
            ...$toastLines,
        ]);
    }

    /**
     * @return array{
     *   summary: string,
     *   actions: list<array<string, mixed>>,
     *   activeToastCount: int
     * }
     */
    private function parseDraftResponse(string $response, int $activeToastCount): array
    {
        $normalized = trim($response);

        if (str_starts_with($normalized, '```')) {
            $normalized = preg_replace('/^```[a-zA-Z0-9_-]*\n|\n```$/', '', $normalized) ?? $normalized;
            $normalized = trim($normalized);
        }

        $payload = json_decode($normalized, true);

        if (!is_array($payload)) {
            throw new SessionSummaryUnavailableException('invalid_curation_draft', 'xAI returned an invalid curation draft.');
        }

        if (is_array($payload['result'] ?? null)) {
            $payload = $payload['result'];
        }

        $summary = trim((string) ($payload['summary'] ?? ''));
        $actions = $payload['actions'] ?? null;

        if (!is_array($actions)) {
            throw new SessionSummaryUnavailableException('invalid_curation_draft', 'xAI returned actions in an invalid format.');
        }

        return [
            'summary' => '' !== $summary ? $summary : 'Draft curation plan generated.',
            'actions' => array_values(array_filter($actions, static fn ($action): bool => is_array($action))),
            'activeToastCount' => $activeToastCount,
        ];
    }

    private function normalizeText(string $value): string
    {
        return preg_replace('/\s+/', ' ', trim($value)) ?? trim($value);
    }

    private function normalizeOptionalText(?string $value): ?string
    {
        if (null === $value || '' === trim($value)) {
            return null;
        }

        return $this->normalizeText($value);
    }
}
