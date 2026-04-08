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
    ): void {
        $assignedToasts = $this->toastRepository->findAssignedActiveForUser($user);

        if ([] === $assignedToasts) {
            $this->transactionalMailer->sendTodoDigest(
                $user,
                "## Top 10 actions\n\nYou currently have no active assigned actions.",
                $originalSubject,
                $messageId,
                $references,
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
        );
    }

    private function buildSystemPrompt(): string
    {
        return implode("\n", [
            'You are helping a Toastit user decide what to do next.',
            'Review only the active actions assigned to that user.',
            'Return a markdown answer titled "## Top 10 actions".',
            'List at most 10 actions, in priority order.',
            'For each action, include:',
            '- the action title',
            '- the workspace name',
            '- a short reason for the ranking',
            '- the due date when available',
            'Be concise and practical.',
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

        foreach ($this->sortToasts($assignedToasts) as $toast) {
            $lines[] = sprintf('- title: %s', $toast->getTitle());
            $lines[] = sprintf('  workspace: %s', $toast->getWorkspace()->getName());
            $lines[] = sprintf('  due_on: %s', $toast->getDueAt()?->format('Y-m-d') ?? 'none');
            $lines[] = sprintf('  created_at: %s', $toast->getCreatedAt()->format('Y-m-d H:i'));
            $lines[] = sprintf('  boosted: %s', $toast->isBoosted() ? 'yes' : 'no');
            $lines[] = sprintf('  vote_count: %d', $toast->getVoteCount());
            $lines[] = sprintf('  description: %s', trim((string) ($toast->getDescription() ?? 'none')));
        }

        return implode("\n", $lines);
    }

    /**
     * @param list<Toast> $assignedToasts
     *
     * @return list<Toast>
     */
    private function sortToasts(array $assignedToasts): array
    {
        usort($assignedToasts, static function (Toast $left, Toast $right): int {
            if ($left->isBoosted() !== $right->isBoosted()) {
                return $left->isBoosted() ? -1 : 1;
            }

            if (null !== $left->getDueAt() && null !== $right->getDueAt() && $left->getDueAt() != $right->getDueAt()) {
                return $left->getDueAt() <=> $right->getDueAt();
            }

            if (null !== $left->getDueAt() xor null !== $right->getDueAt()) {
                return null !== $left->getDueAt() ? -1 : 1;
            }

            if ($left->getVoteCount() !== $right->getVoteCount()) {
                return $right->getVoteCount() <=> $left->getVoteCount();
            }

            return $left->getCreatedAt() <=> $right->getCreatedAt();
        });

        return $assignedToasts;
    }
}
