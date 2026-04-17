<?php

namespace App\Workspace;

use App\Entity\Toast;
use App\Entity\User;
use App\Meeting\SessionSummaryUnavailableException;
use App\Meeting\XaiTextService;

final class ToastCommentSummaryService
{
    public function __construct(
        private readonly XaiTextService $xaiText,
    ) {
    }

    public function summarize(Toast $toast, User $requestedBy): string
    {
        $comments = $toast->getComments()->toArray();
        if ([] === $comments) {
            throw new SessionSummaryUnavailableException('missing_comments', 'No comments are available to summarize.');
        }

        $prompt = [
            sprintf('Toast: %s', $toast->getTitle()),
            sprintf('Requested by: %s', $requestedBy->getDisplayName()),
            '',
            'Summarize the discussion as short markdown.',
            'Required sections in this exact order:',
            '## Summary',
            '## Open questions',
            '## Decisions',
            'Rules:',
            '- Be succinct, concrete, and operational.',
            '- Use only the provided comments.',
            '- If there is no open question or no decision, say "None."',
            '- Do not invent action items.',
            '',
            'Comments:',
        ];

        foreach ($comments as $comment) {
            $prompt[] = sprintf(
                '- %s @ %s: %s',
                $comment->getAuthor()->getDisplayName(),
                $comment->getCreatedAt()->format(\DateTimeInterface::ATOM),
                preg_replace('/\s+/', ' ', trim($comment->getContent())) ?? trim($comment->getContent()),
            );
        }

        return trim($this->xaiText->generateTextForUser(
            $requestedBy,
            'You summarize Toastit comment threads into concise markdown.',
            implode("\n", $prompt),
            [
                'source' => 'toast_comment_summary',
                'workspaceId' => $toast->getWorkspace()->getId(),
                'toastId' => $toast->getId(),
            ],
        ));
    }
}
