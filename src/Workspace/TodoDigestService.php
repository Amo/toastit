<?php

namespace App\Workspace;

use App\Ai\AiPromptTemplateService;
use App\Entity\Toast;
use App\Entity\ToastComment;
use App\Entity\User;
use App\Mailer\TransactionalMailer;
use App\Meeting\XaiTextService;
use App\Repository\ToastCommentRepository;
use App\Repository\ToastRepository;

final class TodoDigestService
{
    private const WEEKLY_SUMMARY_WINDOW_DAYS = 7;

    public function __construct(
        private readonly ToastRepository $toastRepository,
        private readonly ToastCommentRepository $toastCommentRepository,
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

    public function sendWeeklySummary(User $user): void
    {
        $this->sendWeeklySummaryReply($user, null, null, null);
    }

    public function sendDailyCollaborationRecap(User $user, ?\DateTimeImmutable $forDate = null): void
    {
        $targetDate = $forDate ? \DateTimeImmutable::createFromInterface($forDate) : new \DateTimeImmutable('yesterday');
        $windowStart = $targetDate->setTime(0, 0, 0);
        $windowEnd = $targetDate->setTime(23, 59, 59);

        $involvedToastIds = $this->toastRepository->findInvolvedToastIdsForUser($user, 800);
        $statusUpdates = $this->toastRepository->findStatusChangedForToastIdsBetween($involvedToastIds, $windowStart, $windowEnd);
        $recentComments = $this->toastCommentRepository->findForToastIdsBetween($involvedToastIds, $windowStart, $windowEnd, 500);
        $collaborativeComments = array_values(array_filter(
            $recentComments,
            static fn (ToastComment $comment): bool => $comment->getAuthor()->getId() !== $user->getId(),
        ));
        $actionableAssignedToasts = $this->toastRepository->findAssignedActionableForUser($user, 80);

        if ([] === $statusUpdates && [] === $collaborativeComments && [] === $actionableAssignedToasts) {
            $this->transactionalMailer->sendDailyRecap(
                $user,
                sprintf(
                    "## Daily collaboration recap (%s)\n\nNo collaborative updates were detected on your active toasts yesterday.\n\nOpen Toastit to see today's priorities.",
                    $windowStart->format('Y-m-d')
                )
            );

            return;
        }

        $systemPrompt = $this->promptTemplate->resolveSystemPrompt('daily_collaboration_recap_system', '');
        if ('' === trim($systemPrompt)) {
            $systemPrompt = implode("\n", [
                'Return markdown only.',
                'Write a concise daily collaboration recap for one user.',
                'Focus on work completed yesterday and collaborative signals.',
                'Required sections: Yesterday completed, Collaborative signals, Today and upcoming toasts, Attention points for today.',
                'Do not invent events. Only use provided data.',
            ]);
        }

        $userPrompt = $this->promptTemplate->resolveUserPromptTemplate(
            'daily_collaboration_recap_system',
            implode("\n", [
                'User: {{ user_display_name }}',
                'Email: {{ user_email }}',
                'Day covered: {{ day_covered }}',
                '',
                "Status changes yesterday on toasts where the user is involved (author/assignee/commenter):",
                '{{ status_updates }}',
                '',
                'New comments yesterday from collaborators on involved toasts:',
                '{{ collaborative_comments }}',
                '',
                "Today's currently assigned active actions:",
                '{{ assigned_today }}',
                '',
                'Today and upcoming toasts with priority signals (due date, comment activity, boost, votes):',
                '{{ today_and_upcoming }}',
            ]),
            [
                'user_display_name' => $user->getDisplayName(),
                'user_email' => $user->getEmail(),
                'day_covered' => $windowStart->format('Y-m-d'),
                'status_updates' => $this->buildStatusUpdatesText($statusUpdates),
                'collaborative_comments' => $this->buildCollaborativeCommentsText($collaborativeComments),
                'assigned_today' => $this->buildAssignedActionsText($user, $actionableAssignedToasts),
                'today_and_upcoming' => $this->buildTodayAndUpcomingActionsText($actionableAssignedToasts),
            ],
        );

        $rawSummary = $this->xaiText->generateText(
            $systemPrompt,
            $userPrompt,
            [
                'source' => 'daily_collaboration_recap',
                'userId' => $user->getId(),
            ],
        );

        $summary = $this->extractMarkdownResult($rawSummary);
        $this->transactionalMailer->sendDailyRecap($user, $summary);
    }

    public function sendWeeklySummaryReply(
        User $user,
        ?string $originalSubject,
        ?string $messageId,
        ?string $references,
        ?string $replyToAddress = null,
    ): void {
        $windowStart = (new \DateTimeImmutable('today'))->modify(sprintf('-%d days', self::WEEKLY_SUMMARY_WINDOW_DAYS - 1));
        $windowEnd = new \DateTimeImmutable();
        $createdByUser = $this->toastRepository->findCreatedByUserSince($user, $windowStart);
        $createdAndCompletedByUser = $this->toastRepository->findCreatedByUserAndCompletedSince($user, $windowStart);
        $assignedAndCompletedByUser = $this->toastRepository->findAssignedToUserAndCompletedSince($user, $windowStart);

        if ([] === $createdByUser && [] === $createdAndCompletedByUser && [] === $assignedAndCompletedByUser) {
            $languageInstruction = $this->buildWeeklySummaryLanguageInstruction($user);

            $this->transactionalMailer->sendWeeklySummary(
                $user,
                sprintf(
                    "## Weekly operational summary (%s to %s)\n\nNo matching tasks were found during the last 7 calendar days.\n\nLanguage preference: %s.",
                    $windowStart->format('Y-m-d'),
                    $windowEnd->format('Y-m-d'),
                    $languageInstruction
                ),
                $originalSubject,
                $messageId,
                $references,
                $replyToAddress,
            );

            return;
        }

        $systemPrompt = $this->promptTemplate->resolveSystemPrompt('weekly_operational_summary_system', '');
        if ('' === trim($systemPrompt)) {
            $systemPrompt = implode("\n", [
                'Return markdown only.',
                'Write an operational weekly summary for a 1:1 with a manager.',
                'Use section titles in the output language selected by the language instruction.',
                'Required sections: weekly overview, completed tasks, created tasks, watchouts/next week focus.',
                'Use concise bullets, mention concrete outcomes and risks.',
            ]);
        }

        $languageInstruction = $this->buildWeeklySummaryLanguageInstruction($user);
        $userPrompt = $this->promptTemplate->resolveUserPromptTemplate(
            'weekly_operational_summary_system',
            implode("\n", [
                'User: {{ user_display_name }}',
                'Email: {{ user_email }}',
                'Window start: {{ window_start }}',
                'Window end: {{ window_end }}',
                'Language instruction: {{ language_instruction }}',
                '',
                'Created by user and completed during the window:',
                '{{ created_and_completed }}',
                '',
                'Created by user during the window:',
                '{{ created_by_user }}',
                '',
                'Assigned to user and completed during the window:',
                '{{ assigned_and_completed }}',
            ]),
            [
                'user_display_name' => $user->getDisplayName(),
                'user_email' => $user->getEmail(),
                'window_start' => $windowStart->format('Y-m-d'),
                'window_end' => $windowEnd->format('Y-m-d'),
                'language_instruction' => $languageInstruction,
                'created_and_completed' => $this->buildWeeklySummaryTaskList($createdAndCompletedByUser, 'completed_at'),
                'created_by_user' => $this->buildWeeklySummaryTaskList($createdByUser, 'created_at'),
                'assigned_and_completed' => $this->buildWeeklySummaryTaskList($assignedAndCompletedByUser, 'completed_at'),
            ],
        );

        $rawSummary = $this->xaiText->generateText(
            $systemPrompt,
            $userPrompt,
            [
                'source' => 'weekly_operational_summary',
                'userId' => $user->getId(),
            ],
        );
        $summary = $this->extractMarkdownResult($rawSummary);

        $this->transactionalMailer->sendWeeklySummary(
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

    /**
     * @param list<Toast> $toasts
     */
    private function buildWeeklySummaryTaskList(array $toasts, string $timeField): string
    {
        if ([] === $toasts) {
            return 'none';
        }

        $lines = [];

        foreach ($toasts as $toast) {
            $lines[] = sprintf('  id: %d', $toast->getId() ?? 0);
            $lines[] = sprintf('- title: %s', $toast->getTitle());
            $lines[] = sprintf('  workspace: %s', $toast->getWorkspace()->getName());
            $lines[] = sprintf('  assignee: %s', $toast->getOwner()?->getDisplayName() ?? 'none');
            $lines[] = sprintf('  author: %s', $toast->getAuthor()->getDisplayName());
            $lines[] = sprintf('  status: %s', $toast->getStatus());
            $lines[] = sprintf('  created_at: %s', $toast->getCreatedAt()->format('Y-m-d H:i'));
            $lines[] = sprintf('  completed_at: %s', $toast->getStatusChangedAt()?->format('Y-m-d H:i') ?? 'none');
            $lines[] = sprintf('  due_on: %s', $toast->getDueAt()?->format('Y-m-d') ?? 'none');
            $lines[] = sprintf('  key_time: %s', 'completed_at' === $timeField
                ? ($toast->getStatusChangedAt()?->format('Y-m-d H:i') ?? 'none')
                : $toast->getCreatedAt()->format('Y-m-d H:i'));
            $lines[] = sprintf('  description: %s', trim((string) ($toast->getDescription() ?? 'none')));
            $lines[] = sprintf('  decision_notes: %s', trim((string) ($toast->getDiscussionNotes() ?? 'none')));
            $lines[] = sprintf('  follow_up_text: %s', trim((string) ($toast->getFollowUp() ?? 'none')));
            $lines[] = sprintf('  follow_up_items: %s', $this->buildFollowUpItemsText($toast));
            $lines[] = sprintf('  latest_comments: %s', $this->buildLatestCommentsText($toast));
        }

        return implode("\n", $lines);
    }

    private function buildWeeklySummaryLanguageInstruction(User $user): string
    {
        $preferredLanguage = $user->getInboundRewordLanguage();
        if (null === $preferredLanguage || '' === trim($preferredLanguage)) {
            return 'Auto-detect language from task content and keep a single language across the whole summary.';
        }

        return sprintf('Force output language to: %s.', User::getInboundRewordLanguageLabel($preferredLanguage));
    }

    private function buildFollowUpItemsText(Toast $toast): string
    {
        $items = $toast->getFollowUpItems();
        if ([] === $items) {
            return 'none';
        }

        $lines = array_map(static function (array $item): string {
            return sprintf(
                '%s (owner_id: %s, due_on: %s)',
                trim((string) ($item['title'] ?? 'untitled')),
                null !== ($item['ownerId'] ?? null) ? (string) $item['ownerId'] : 'none',
                trim((string) ($item['dueOn'] ?? '')) ?: 'none',
            );
        }, $items);

        return implode(' | ', $lines);
    }

    private function buildLatestCommentsText(Toast $toast): string
    {
        $comments = $toast->getComments()->toArray();
        if ([] === $comments) {
            return 'none';
        }

        $latestComments = array_slice($comments, -3);
        $formatted = array_map(static function (mixed $comment): string {
            if (!$comment instanceof \App\Entity\ToastComment) {
                return '';
            }

            return sprintf(
                '%s: %s',
                $comment->getAuthor()->getDisplayName(),
                trim($comment->getContent()),
            );
        }, $latestComments);

        $formatted = array_values(array_filter($formatted, static fn (string $value): bool => '' !== $value));

        return [] === $formatted ? 'none' : implode(' | ', $formatted);
    }

    /**
     * @param list<Toast> $toasts
     */
    private function buildStatusUpdatesText(array $toasts): string
    {
        if ([] === $toasts) {
            return 'none';
        }

        $lines = [];
        foreach ($toasts as $toast) {
            $lines[] = sprintf('  id: %d', $toast->getId() ?? 0);
            $lines[] = sprintf('- title: %s', $toast->getTitle());
            $lines[] = sprintf('  workspace: %s', $toast->getWorkspace()->getName());
            $lines[] = sprintf('  new_status: %s', $toast->getStatus());
            $lines[] = sprintf('  changed_at: %s', $toast->getStatusChangedAt()?->format('Y-m-d H:i') ?? 'none');
            $lines[] = sprintf('  assignee: %s', $toast->getOwner()?->getDisplayName() ?? 'none');
            $lines[] = sprintf('  author: %s', $toast->getAuthor()->getDisplayName());
        }

        return implode("\n", $lines);
    }

    /**
     * @param list<ToastComment> $comments
     */
    private function buildCollaborativeCommentsText(array $comments): string
    {
        if ([] === $comments) {
            return 'none';
        }

        $lines = [];
        foreach ($comments as $comment) {
            $lines[] = sprintf('  toast_id: %d', $comment->getToast()->getId() ?? 0);
            $lines[] = sprintf('- toast_title: %s', $comment->getToast()->getTitle());
            $lines[] = sprintf('  workspace: %s', $comment->getToast()->getWorkspace()->getName());
            $lines[] = sprintf('  author: %s', $comment->getAuthor()->getDisplayName());
            $lines[] = sprintf('  created_at: %s', $comment->getCreatedAt()->format('Y-m-d H:i'));
            $lines[] = sprintf('  content: %s', trim($comment->getContent()));
        }

        return implode("\n", $lines);
    }

    /**
     * @param list<Toast> $toasts
     */
    private function buildTodayAndUpcomingActionsText(array $toasts): string
    {
        if ([] === $toasts) {
            return 'none';
        }

        $today = new \DateTimeImmutable('today');
        $sevenDaysFromNow = $today->modify('+7 days');
        $lines = [];

        foreach ($this->assignedToastPriority->sort($toasts) as $toast) {
            $latestComment = null;
            $comments = $toast->getComments()->toArray();
            if ([] !== $comments) {
                $last = end($comments);
                if ($last instanceof ToastComment) {
                    $latestComment = $last;
                }
            }

            $dueAt = $toast->getDueAt();
            $dueBucket = 'no_due_date';
            if ($dueAt instanceof \DateTimeImmutable) {
                if ($dueAt < $today) {
                    $dueBucket = 'overdue';
                } elseif ($dueAt->format('Y-m-d') === $today->format('Y-m-d')) {
                    $dueBucket = 'today';
                } elseif ($dueAt <= $sevenDaysFromNow) {
                    $dueBucket = 'next_7_days';
                } else {
                    $dueBucket = 'later';
                }
            }

            $lines[] = sprintf('  id: %d', $toast->getId() ?? 0);
            $lines[] = sprintf('- title: %s', $toast->getTitle());
            $lines[] = sprintf('  workspace: %s', $toast->getWorkspace()->getName());
            $lines[] = sprintf('  status: %s', $toast->getStatus());
            $lines[] = sprintf('  due_on: %s', $dueAt?->format('Y-m-d') ?? 'none');
            $lines[] = sprintf('  due_bucket: %s', $dueBucket);
            $lines[] = sprintf('  boosted: %s', $toast->isBoosted() ? 'yes' : 'no');
            $lines[] = sprintf('  vote_count: %d', $toast->getVoteCount());
            $lines[] = sprintf('  comment_count: %d', count($comments));
            $lines[] = sprintf('  last_comment_at: %s', $latestComment?->getCreatedAt()->format('Y-m-d H:i') ?? 'none');
            $lines[] = sprintf('  last_comment_author: %s', $latestComment?->getAuthor()->getDisplayName() ?? 'none');
        }

        return implode("\n", $lines);
    }
}
