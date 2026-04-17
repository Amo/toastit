<?php

namespace App\Meeting;

use App\Entity\Toast;
use App\Entity\ToastingSession;
use App\Entity\Workspace;
use App\Workspace\WorkspaceWorkflowService;

final class ToastingSessionSummaryBuilder
{
    public function __construct(
        private readonly WorkspaceWorkflowService $workspaceWorkflow,
    ) {
    }

    /**
     * @return array{
     *     sessionId: int,
     *     startedAt: string,
     *     endedAt: string,
     *     isActive: bool,
     *     sourceItemCount: int,
     *     prompt: string
     * }
     */
    public function buildPrompt(Workspace $workspace, ToastingSession $session): array
    {
        $startedAt = $session->getStartedAt();
        $endedAt = $session->getEndedAt() ?? new \DateTimeImmutable();
        $participants = $this->workspaceWorkflow->getWorkspaceInvitees($workspace);
        $relevantItems = array_values(array_filter(
            $workspace->getItems()->toArray(),
            fn (Toast $item): bool => $this->isRelevantToSession($item, $startedAt, $endedAt),
        ));

        usort($relevantItems, static fn (Toast $left, Toast $right): int => $left->getCreatedAt() <=> $right->getCreatedAt());

        $prompt = [
            sprintf('Workspace: %s', $workspace->getName()),
            sprintf('Session window: %s -> %s', $startedAt->format(\DateTimeInterface::ATOM), $endedAt->format(\DateTimeInterface::ATOM)),
            sprintf('Started by: %s', $session->getStartedBy()->getDisplayName()),
            sprintf('Ended by: %s', $session->getEndedBy()?->getDisplayName() ?? 'Session still active'),
            '',
            'Toast status legend:',
            '- status=pending: the toast is still active and in progress.',
            '- status=ready: the assignee marked the toast as done and ready for review/decision in toasting mode.',
            '- status=toasted: the toast was discussed and treated during a toasting session, usually with notes and/or follow-ups.',
            '- status=discarded: the toast was explicitly declined/rejected.',
            '- followUpToasts: child toasts created as explicit next steps from a toasted item.',
            '- sessionComments: comments written during the session window; they are discussion signals and context, not automatic decisions by themselves.',
            '',
            'Participants:',
            ...array_map(
                static fn ($participant): string => sprintf('- %s <%s>', $participant->getDisplayName(), $participant->getPublicEmail()),
                $participants
            ),
            '',
            'Relevant toasts for this session:',
            ...($relevantItems !== [] ? array_map(
                fn (Toast $item): string => $this->formatToast($item, $startedAt, $endedAt),
                $relevantItems
            ) : ['- None detected for the session window.']),
            '',
            'Expected output:',
            'Produce the answer in English as Markdown.',
            'Use exactly these sections:',
            '## Decisions',
            '## Next steps by member',
            '## Suggestions',
            'Requirements:',
            '- Capture every explicit decision grounded in the provided data.',
            '- In the section "Next steps by member", include only explicit followUpToasts created from toasted items plus still-open standalone toasts created during the session window.',
            '- Never restate a source toast that was toasted during the session as a new next step unless a separate followUpToast was created for it.',
            '- In that section, group only the eligible next steps by owner.',
            '- Include due dates when available.',
            '- If a next step has no owner, place it under an "Unassigned" group.',
            '- Use comments as supporting context, concerns, or rationale, but do not present them as decisions unless the rest of the data clearly confirms that.',
            '- Use the toast status and discussion legend above when inferring what happened during the session.',
            '- Suggest practical meeting/process improvements based only on the provided context.',
            '- If data is missing, say so explicitly instead of inventing it.',
        ];

        return [
            'sessionId' => $session->getId() ?? 0,
            'startedAt' => $startedAt->format(\DateTimeInterface::ATOM),
            'endedAt' => $endedAt->format(\DateTimeInterface::ATOM),
            'isActive' => $session->isActive(),
            'sourceItemCount' => count($relevantItems),
            'prompt' => implode("\n", $prompt),
        ];
    }

    private function isRelevantToSession(Toast $item, \DateTimeImmutable $startedAt, \DateTimeImmutable $endedAt): bool
    {
        if ($this->isWithinWindow($item->getCreatedAt(), $startedAt, $endedAt)) {
            return true;
        }

        if (null !== $item->getStatusChangedAt() && $this->isWithinWindow($item->getStatusChangedAt(), $startedAt, $endedAt)) {
            return true;
        }

        foreach ($item->getComments() as $comment) {
            if ($this->isWithinWindow($comment->getCreatedAt(), $startedAt, $endedAt)) {
                return true;
            }
        }

        return false;
    }

    private function isWithinWindow(\DateTimeImmutable $value, \DateTimeImmutable $startedAt, \DateTimeImmutable $endedAt): bool
    {
        return $value >= $startedAt && $value <= $endedAt;
    }

    private function formatToast(Toast $item, \DateTimeImmutable $startedAt, \DateTimeImmutable $endedAt): string
    {
        $lines = [
            sprintf('- Toast #%d: %s', $item->getId(), $item->getTitle()),
            sprintf('  status: %s', $item->getStatus()),
            sprintf('  nextStepEligible: %s', $this->isNextStepEligible($item, $startedAt, $endedAt) ? 'yes' : 'no'),
            sprintf('  author: %s', $item->getAuthor()->getDisplayName()),
            sprintf('  owner: %s', $item->getOwner()?->getDisplayName() ?? 'unassigned'),
            sprintf('  createdAt: %s', $item->getCreatedAt()->format(\DateTimeInterface::ATOM)),
        ];

        if (null !== $item->getDueAt()) {
            $lines[] = sprintf('  dueOn: %s', $item->getDueAt()->format('Y-m-d'));
        }

        if (null !== $item->getStatusChangedAt()) {
            $lines[] = sprintf('  statusChangedAt: %s', $item->getStatusChangedAt()->format(\DateTimeInterface::ATOM));
        }

        if (null !== $item->getDescription() && '' !== trim($item->getDescription())) {
            $lines[] = sprintf('  description: %s', $this->normalizeText($item->getDescription()));
        }

        if (null !== $item->getDiscussionNotes() && '' !== trim($item->getDiscussionNotes())) {
            $lines[] = sprintf('  discussionNotes: %s', $this->normalizeText($item->getDiscussionNotes()));
        }

        $followUps = $item->getFollowUpChildren()->toArray();
        usort($followUps, static fn (Toast $left, Toast $right): int => $left->getCreatedAt() <=> $right->getCreatedAt());

        if ($followUps !== []) {
            $lines[] = '  followUpToasts:';

            foreach ($followUps as $followUp) {
                $lines[] = sprintf(
                    '    - %s | owner: %s | dueOn: %s | status: %s',
                    $followUp->getTitle(),
                    $followUp->getOwner()?->getDisplayName() ?? 'unassigned',
                    $followUp->getDueAt()?->format('Y-m-d') ?? 'none',
                    $followUp->getStatus(),
                );
            }
        }

        $sessionComments = [];
        foreach ($item->getComments() as $comment) {
            if (!$this->isWithinWindow($comment->getCreatedAt(), $startedAt, $endedAt)) {
                continue;
            }

            $sessionComments[] = sprintf(
                '    - %s @ %s: %s',
                $comment->getAuthor()->getDisplayName(),
                $comment->getCreatedAt()->format(\DateTimeInterface::ATOM),
                $this->normalizeText($comment->getContent())
            );
        }

        if ($sessionComments !== []) {
            $lines[] = '  sessionComments:';
            array_push($lines, ...$sessionComments);
        }

        return implode("\n", $lines);
    }

    private function normalizeText(string $value): string
    {
        return preg_replace('/\s+/', ' ', trim($value)) ?? trim($value);
    }

    private function isNextStepEligible(Toast $item, \DateTimeImmutable $startedAt, \DateTimeImmutable $endedAt): bool
    {
        if (null !== $item->getPreviousItem()) {
            return true;
        }

        if ($item->isToasted() || $item->isVetoed()) {
            return false;
        }

        return $this->isWithinWindow($item->getCreatedAt(), $startedAt, $endedAt);
    }
}
