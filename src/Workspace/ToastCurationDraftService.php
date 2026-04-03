<?php

namespace App\Workspace;

use App\Entity\Toast;
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
    ) {
    }

    /**
     * @return array{
     *   summary: string,
     *   actions: list<array<string, mixed>>,
     *   activeToastCount: int
     * }
     */
    public function generateDraft(Workspace $workspace): array
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

        $response = $this->xaiText->generateText(
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
            $this->buildWorkspaceContext($workspace, $activeItems),
        );

        return $this->parseDraftResponse($response, count($activeItems));
    }

    /**
     * @param list<Toast> $activeItems
     */
    private function buildWorkspaceContext(Workspace $workspace, array $activeItems): string
    {
        $participants = $this->workspaceWorkflow->getWorkspaceInvitees($workspace);
        $participantLines = array_map(
            static fn ($participant): string => sprintf('- %d: %s', $participant->getId(), $participant->getDisplayName()),
            array_filter($participants, static fn ($participant): bool => null !== $participant->getId()),
        );

        $toastLines = array_map(function (Toast $toast): string {
            $lines = [
                sprintf('- toastId: %d', $toast->getId()),
                sprintf('  title: %s', $toast->getTitle()),
                sprintf('  status: %s / discussion: %s', $toast->getStatus(), $toast->getDiscussionStatus()),
                sprintf('  author: %s', $toast->getAuthor()->getDisplayName()),
                sprintf('  ownerId: %s', null !== $toast->getOwner()?->getId() ? (string) $toast->getOwner()->getId() : 'null'),
                sprintf('  ownerName: %s', $toast->getOwner()?->getDisplayName() ?? 'unassigned'),
                sprintf('  voteCount: %d', $toast->getVoteCount()),
                sprintf('  isBoosted: %s', $toast->isBoosted() ? 'true' : 'false'),
            ];

            if (null !== $toast->getDueAt()) {
                $lines[] = sprintf('  dueOn: %s', $toast->getDueAt()->format('Y-m-d'));
            }

            if (null !== $toast->getDescription() && '' !== trim($toast->getDescription())) {
                $lines[] = sprintf('  description: %s', $this->normalizeText($toast->getDescription()));
            }

            $comments = [];
            foreach ($toast->getComments() as $comment) {
                $comments[] = sprintf(
                    '    - %s: %s',
                    $comment->getAuthor()->getDisplayName(),
                    $this->normalizeText($comment->getContent()),
                );
            }

            if ([] !== $comments) {
                $lines[] = '  comments:';
                array_push($lines, ...$comments);
            }

            return implode("\n", $lines);
        }, $activeItems);

        return implode("\n", [
            sprintf('Workspace: %s', $workspace->getName()),
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
}
