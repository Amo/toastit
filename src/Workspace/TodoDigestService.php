<?php

namespace App\Workspace;

use App\Entity\Toast;
use App\Entity\User;
use App\Mailer\TransactionalMailer;
use App\Meeting\XaiTextService;
use App\Repository\ToastRepository;

final class TodoDigestService
{
    public function __construct(
        private readonly ToastRepository $toastRepository,
        private readonly XaiTextService $xaiText,
        private readonly TransactionalMailer $transactionalMailer,
        private readonly AssignedToastPriorityService $assignedToastPriority,
    ) {
    }

    public function sendTodoDigest(User $user): void
    {
        $this->sendTodoDigestReply($user, null, null, null);
    }

    public function sendTodoDigestReply(
        User $user,
        ?string $originalSubject,
        ?string $messageId,
        ?string $references,
        ?string $replyToAddress = null,
    ): void {
        $assignedToasts = $this->toastRepository->findAssignedActiveForUser($user);

        if ([] === $assignedToasts) {
            $this->transactionalMailer->sendTodoDigest(
                $user,
                "## Top 10 actions\n\nYou currently have no active assigned actions.",
                $originalSubject,
                $messageId,
                $references,
                $replyToAddress,
            );

            return;
        }

        $summary = $this->xaiText->generateText(
            $this->buildSystemPrompt(),
            $this->buildUserPrompt($user, $assignedToasts),
            [
                'source' => 'todo_digest',
                'userId' => $user->getId(),
            ],
        );

        $this->transactionalMailer->sendTodoDigest(
            $user,
            $summary,
            $originalSubject,
            $messageId,
            $references,
            $replyToAddress,
        );
    }

    private function buildSystemPrompt(): string
    {
        return implode("\n", [
            'You are helping a Toastit user decide what to do next.',
            'Review only the active actions assigned to that user.',
            'Return a markdown answer titled "## Top 10 actions".',
            'List at most 10 actions, in priority order.',
            'Use exactly one concise line per action in this format:',
            '- [<id>] <title>, <date>, <assignee>',
            'Rules:',
            '- <id> is the task id as an integer',
            '- <date> is YYYY-MM-DD or "none"',
            '- <assignee> is the assignee display name',
            '- Do not include workspace, rationale, prose, or extra sections',
            '- Do not add any text before or after the list',
        ]);
    }

    /**
     * @param list<Toast> $assignedToasts
     */
    private function buildUserPrompt(User $user, array $assignedToasts): string
    {
        $lines = [
            sprintf('User: %s', $user->getDisplayName()),
            sprintf('Email: %s', $user->getEmail()),
            sprintf('Today: %s', (new \DateTimeImmutable())->format('Y-m-d')),
            'Assigned active actions:',
        ];

        foreach ($this->assignedToastPriority->sort($assignedToasts) as $toast) {
            $lines[] = sprintf('  id: %d', $toast->getId() ?? 0);
            $lines[] = sprintf('- title: %s', $toast->getTitle());
            $lines[] = sprintf('  workspace: %s', $toast->getWorkspace()->getName());
            $lines[] = sprintf('  due_on: %s', $toast->getDueAt()?->format('Y-m-d') ?? 'none');
            $lines[] = sprintf('  assignee: %s', $toast->getOwner()?->getDisplayName() ?? $user->getDisplayName());
            $lines[] = sprintf('  created_at: %s', $toast->getCreatedAt()->format('Y-m-d H:i'));
            $lines[] = sprintf('  boosted: %s', $toast->isBoosted() ? 'yes' : 'no');
            $lines[] = sprintf('  vote_count: %d', $toast->getVoteCount());
            $lines[] = sprintf('  description: %s', trim((string) ($toast->getDescription() ?? 'none')));
        }

        return implode("\n", $lines);
    }
}
