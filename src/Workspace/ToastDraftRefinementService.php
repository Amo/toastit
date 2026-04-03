<?php

namespace App\Workspace;

use App\Entity\Workspace;
use App\Meeting\SessionSummaryUnavailableException;
use App\Meeting\XaiTextService;

final class ToastDraftRefinementService
{
    public function __construct(
        private readonly XaiTextService $xaiText,
        private readonly WorkspaceWorkflowService $workspaceWorkflow,
    ) {
    }

    /**
     * @return array{title: string, description: string, ownerId: ?int, dueOn: ?string}
     */
    public function refine(Workspace $workspace, string $title, ?string $description): array
    {
        $title = trim($title);
        $description = trim((string) $description);

        if ('' === $title && '' === $description) {
            throw new SessionSummaryUnavailableException('missing_input', 'A title or description is required.');
        }

        $response = $this->xaiText->generateText(
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
- You may suggest a due date if the scope, urgency, constraints, and likely amount of work make a date reasonably inferable.
- Do not invent facts, owners, dates, budgets, or decisions that are not present in the source.
- Output must follow this exact format:
TITLE: <single line>
ASSIGNEE: <exact participant display name or NONE>
DUE_ON: <YYYY-MM-DD or NONE>
DESCRIPTION:
<markdown description>
PROMPT,
            sprintf(
                "Workspace participants:\n%s\n\nCurrent title:\n%s\n\nCurrent description:\n%s",
                $this->formatParticipants($workspace),
                '' !== $title ? $title : '(empty)',
                '' !== $description ? $description : '(empty)',
            ),
        );

        return $this->parseResponse($workspace, $response);
    }

    /**
     * @return array{title: string, description: string, ownerId: ?int, dueOn: ?string}
     */
    private function parseResponse(Workspace $workspace, string $response): array
    {
        $normalized = trim(str_replace("\r\n", "\n", $response));

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

        $resolvedDueOn = null;
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
}
