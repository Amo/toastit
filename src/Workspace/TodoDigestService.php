<?php

namespace App\Workspace;

use App\Ai\AiPromptTemplateService;
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
        private readonly AiPromptTemplateService $promptTemplate,
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

        $systemPrompt = $this->promptTemplate->resolveSystemPrompt('todo_digest_system', '');
        if ('' === trim($systemPrompt)) {
            $systemPrompt = "Return a markdown answer titled \"## Top 10 actions\" with one line per action.";
        }

        $assignedActionsText = $this->buildAssignedActionsText($user, $assignedToasts);
        $userPrompt = $this->promptTemplate->resolveUserPromptTemplate(
            'todo_digest_system',
            "User: {{ user_display_name }}\nEmail: {{ user_email }}\nToday: {{ today_date }}\nAssigned active actions:\n{{ assigned_actions_text }}",
            [
                'user_display_name' => $user->getDisplayName(),
                'user_email' => $user->getEmail(),
                'today_date' => (new \DateTimeImmutable())->format('Y-m-d'),
                'assigned_actions_text' => $assignedActionsText,
            ],
        );

        $rawSummary = $this->xaiText->generateText(
            $systemPrompt,
            $userPrompt,
            [
                'source' => 'todo_digest',
                'userId' => $user->getId(),
            ],
        );

        $summary = $this->extractMarkdownResult($rawSummary);

        $this->transactionalMailer->sendTodoDigest(
            $user,
            $summary,
            $originalSubject,
            $messageId,
            $references,
            $replyToAddress,
        );
    }

    /**
     * @param list<Toast> $assignedToasts
     */
    private function buildAssignedActionsText(User $user, array $assignedToasts): string
    {
        $lines = [];

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

    private function extractMarkdownResult(string $rawSummary): string
    {
        $payload = json_decode(trim($rawSummary), true);
        if (is_array($payload) && is_array($payload['result'] ?? null) && is_string($payload['result']['markdown'] ?? null)) {
            $markdown = trim($payload['result']['markdown']);

            return '' !== $markdown ? $markdown : trim($rawSummary);
        }

        return trim($rawSummary);
    }
}
