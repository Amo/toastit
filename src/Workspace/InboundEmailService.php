<?php

namespace App\Workspace;

use App\Entity\Toast;
use App\Entity\ToastComment;
use App\Entity\ToastReplyToken;
use App\Entity\User;
use App\Entity\Workspace;
use App\Meeting\SessionSummaryUnavailableException;
use App\Routing\AppUrlGenerator;
use App\Security\JwtTokenService;
use App\Repository\WorkspaceRepository;
use App\Repository\UserRepository;
use App\Repository\ToastRepository;
use Doctrine\ORM\EntityManagerInterface;
use App\Mailer\TransactionalMailer;

final class InboundEmailService
{
    public function __construct(
        private readonly InboundEmailAddressService $inboundEmailAddress,
        private readonly UserRepository $userRepository,
        private readonly WorkspaceRepository $workspaceRepository,
        private readonly ToastRepository $toastRepository,
        private readonly InboxWorkspaceService $inboxWorkspace,
        private readonly ToastCreationService $toastCreation,
        private readonly TodoDigestService $todoDigest,
        private readonly ToastDraftRefinementService $toastDraftRefinement,
        private readonly ToastReplyTokenService $toastReplyToken,
        private readonly InboundReplyAddressService $inboundReplyAddress,
        private readonly WorkspaceSuggestionService $workspaceSuggestion,
        private readonly TransactionalMailer $transactionalMailer,
        private readonly ToastTransferService $toastTransfer,
        private readonly WorkspaceWorkflowService $workspaceWorkflow,
        private readonly JwtTokenService $jwtTokenService,
        private readonly AppUrlGenerator $appUrlGenerator,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function ingest(
        string $recipient,
        string $from,
        ?string $subject = null,
        ?string $textBody = null,
        ?string $htmlBody = null,
        ?string $messageId = null,
        ?string $inReplyTo = null,
        ?string $references = null,
    ): ?InboundEmailResult {
        if (null !== $replyResult = $this->handleReplyRecipient($recipient, $subject, $textBody, $htmlBody, $messageId, $inReplyTo, $references)) {
            return $replyResult;
        }

        $user = null;
        $userAlias = $this->inboundEmailAddress->resolveUserAlias($recipient);

        if (null !== $userAlias) {
            $candidateUser = $this->userRepository->findOneByInboundEmailAlias($userAlias);
            if ($candidateUser instanceof User) {
                $user = $candidateUser;
            }
        } else {
            $userEmail = $this->inboundEmailAddress->resolveUserEmail($recipient);
            if (null === $userEmail) {
                return null;
            }

            $candidateUser = $this->userRepository->findOneByNormalizedEmail($userEmail);
            if ($candidateUser instanceof User) {
                $user = $candidateUser;
            }
        }

        if (!$user instanceof User || $user->isDeleted()) {
            return null;
        }

        if ($this->isTodoDigestSubject($subject)) {
            $replyBody = $this->extractBodyText($textBody, $htmlBody);

            if ($this->containsReplyCommandIntent($replyBody)) {
                $this->handleTodoDigestReplyCommands(
                    $user,
                    $replyBody,
                    $subject,
                    $messageId,
                    $inReplyTo,
                    $references,
                );

                return InboundEmailResult::todoDigestSent();
            }

            $this->todoDigest->sendTodoDigestReply(
                $user,
                $subject,
                $messageId ?? $inReplyTo,
                $references,
                $this->inboundEmailAddress->buildAddressForUser($user),
            );

            return InboundEmailResult::todoDigestSent();
        }

        $workspace = $this->inboxWorkspace->getOrCreateInboxWorkspace($user);
        $originalTitle = $this->buildTitle($from, $subject, $textBody, $htmlBody);
        $originalDescription = $this->buildDescription($textBody, $htmlBody);
        $toast = $this->toastCreation->createToast(
            $workspace,
            $user,
            $originalTitle,
            $originalDescription,
        );

        $wasRewordedByAi = $this->applyAutomaticRefinementSuggestions($workspace, $toast, $user);
        $workspaceSuggestion = $this->workspaceSuggestion->suggestWorkspace($user, $toast->getTitle(), $toast->getDescription());
        $toast = $this->applyAutomaticWorkspaceSuggestion($toast, $user, $workspaceSuggestion);
        $this->entityManager->flush();

        $replyToAddress = $this->buildToastReplyAddress($user, $toast);

        if (null !== $replyToAddress) {
            $this->transactionalMailer->sendInboundToastAcknowledgement(
                $toast,
                $replyToAddress,
                $workspaceSuggestion,
                $wasRewordedByAi,
                $originalTitle,
                $originalDescription,
                $subject,
                $messageId,
                $references,
            );
        }

        return InboundEmailResult::toastCreated($toast);
    }

    private function handleTodoDigestReplyCommands(
        User $user,
        string $replyBody,
        ?string $subject,
        ?string $messageId,
        ?string $inReplyTo,
        ?string $references,
    ): void {
        $segments = preg_split('/[\r\n;]+/', $replyBody) ?: [];
        $results = [];
        $appliedCount = 0;

        foreach ($segments as $segment) {
            $segment = trim($segment);
            if ('' === $segment || str_starts_with($segment, '>')) {
                continue;
            }

            if (1 === preg_match('/^(on|le)\b.+wrote:?$/i', $segment)) {
                continue;
            }

            if (!preg_match('/^#(\d+)\s+(.+)$/', $segment, $taskMatch)) {
                $results[] = [
                    'action' => 'unknown',
                    'status' => 'pending_confirmation',
                    'taskId' => 0,
                    'summary' => $this->truncate($segment, 80),
                    'details' => 'Missing task id. Prefix each command with #<taskId>.',
                    'options' => [],
                ];
                continue;
            }

            $taskId = (int) $taskMatch[1];
            $instruction = trim($taskMatch[2]);
            $toast = $this->resolveUserVisibleToast($user, $taskId);

            if (!$toast instanceof Toast) {
                $results[] = [
                    'action' => 'reference',
                    'status' => 'failed',
                    'taskId' => $taskId,
                    'summary' => $this->truncate($instruction, 80),
                    'details' => 'Task not found or not accessible for your account.',
                    'options' => [],
                ];
                continue;
            }

            $workspaceSuggestion = $this->workspaceSuggestion->suggestWorkspace($user, $toast->getTitle(), $toast->getDescription());
            $parsedActions = $this->parseReplyActions($instruction, $toast, $user, $workspaceSuggestion);

            foreach ($parsedActions as $action) {
                if ('pending_confirmation' === $action['status']) {
                    $results[] = [
                        'action' => $action['action'],
                        'status' => 'pending_confirmation',
                        'taskId' => $toast->getId(),
                        'summary' => $action['summary'],
                        'details' => $action['details'],
                        'options' => $this->buildConfirmationOptions($toast, $user, $action),
                    ];
                    continue;
                }

                $execution = $this->applyReplyAction($toast, $user, $action, $workspaceSuggestion);
                if (null !== $execution['toast']) {
                    $toast = $execution['toast'];
                }

                if ($execution['applied']) {
                    ++$appliedCount;
                }

                $results[] = [
                    'action' => $action['action'],
                    'status' => $execution['applied'] ? 'applied' : 'failed',
                    'taskId' => $toast->getId(),
                    'summary' => $action['summary'],
                    'details' => $execution['details'],
                    'options' => [],
                ];
            }
        }

        if ($appliedCount > 0) {
            $this->entityManager->flush();
        }

        $this->transactionalMailer->sendInboundActionSummary(
            $user,
            'Todo digest commands',
            $results,
            $this->inboundEmailAddress->buildAddressForUser($user),
            null,
            $subject,
            $messageId ?? $inReplyTo,
            $references,
        );
    }

    private function handleReplyRecipient(
        string $recipient,
        ?string $subject,
        ?string $textBody,
        ?string $htmlBody,
        ?string $messageId,
        ?string $inReplyTo,
        ?string $references,
    ): ?InboundEmailResult {
        $replyRecipient = $this->inboundReplyAddress->parseAddress($recipient);

        if (null === $replyRecipient) {
            return null;
        }

        $replyToken = $this->toastReplyToken->findValid($replyRecipient['selector'], $replyRecipient['token']);

        if (!$replyToken instanceof ToastReplyToken) {
            return null;
        }

        $toast = $replyToken->getToast();
        $actor = $replyToken->getUser();
        $workspaceSuggestion = $this->workspaceSuggestion->suggestWorkspace($actor, $toast->getTitle(), $toast->getDescription());
        $parsedActions = $this->parseReplyActions(
            $this->extractBodyText($textBody, $htmlBody),
            $toast,
            $actor,
            $workspaceSuggestion,
        );

        if ([] === $parsedActions) {
            return InboundEmailResult::todoDigestSent();
        }

        $results = [];
        $appliedCount = 0;

        foreach ($parsedActions as $action) {
            if ('pending_confirmation' === $action['status']) {
                $results[] = [
                    'action' => $action['action'],
                    'status' => 'pending_confirmation',
                    'taskId' => $toast->getId(),
                    'summary' => $action['summary'],
                    'details' => $action['details'],
                    'options' => $this->buildConfirmationOptions($toast, $actor, $action),
                ];
                continue;
            }

            $execution = $this->applyReplyAction($toast, $actor, $action, $workspaceSuggestion);

            if (null !== $execution['toast']) {
                $toast = $execution['toast'];
            }

            if ($execution['applied']) {
                ++$appliedCount;
            }

            $results[] = [
                'action' => $action['action'],
                'status' => $execution['applied'] ? 'applied' : 'failed',
                'taskId' => $toast->getId(),
                'summary' => $action['summary'],
                'details' => $execution['details'],
                'options' => [],
            ];
        }

        if ($appliedCount > 0) {
            $this->entityManager->flush();
        }

        $this->transactionalMailer->sendToastReplyActionResult(
            $toast,
            $results,
            $recipient,
            $subject,
            $messageId ?? $inReplyTo,
            $references,
        );

        return InboundEmailResult::todoDigestSent();
    }

    private function buildTitle(string $from, ?string $subject, ?string $textBody, ?string $htmlBody): string
    {
        $subject = trim((string) $subject);
        if ('' !== $subject) {
            return $this->truncate($subject, 180);
        }

        $preview = $this->extractPreviewText($textBody, $htmlBody);
        if ('' !== $preview) {
            return $this->truncate($preview, 180);
        }

        return $this->truncate(sprintf('Email from %s', trim($from)), 180);
    }

    private function buildDescription(?string $textBody, ?string $htmlBody): ?string
    {
        $body = $this->extractBodyText($textBody, $htmlBody);

        return '' !== $body ? $body : '(No email body provided)';
    }

    private function extractPreviewText(?string $textBody, ?string $htmlBody): string
    {
        $body = $this->extractBodyText($textBody, $htmlBody);
        if ('' === $body) {
            return '';
        }

        $lines = preg_split('/\R+/', $body) ?: [];

        foreach ($lines as $line) {
            $line = trim($line);
            if ('' !== $line) {
                return $line;
            }
        }

        return '';
    }

    private function extractBodyText(?string $textBody, ?string $htmlBody): string
    {
        $textBody = trim((string) $textBody);
        if ('' !== $textBody) {
            return $textBody;
        }

        $htmlBody = trim((string) $htmlBody);
        if ('' === $htmlBody) {
            return '';
        }

        $normalizedHtml = preg_replace('/<br\s*\/?>/i', "\n", $htmlBody) ?? $htmlBody;
        $normalizedHtml = preg_replace('/<\/p>/i', "</p>\n", $normalizedHtml) ?? $normalizedHtml;
        $text = html_entity_decode(strip_tags($normalizedHtml), ENT_QUOTES | ENT_HTML5);
        $text = preg_replace("/\n{3,}/", "\n\n", $text ?? '') ?? '';

        return trim($text);
    }

    private function truncate(string $value, int $maxLength): string
    {
        return mb_strlen($value) <= $maxLength
            ? $value
            : rtrim(mb_substr($value, 0, $maxLength - 1)).'…';
    }

    private function workspaceAccessWorkspaceOrNull(int $workspaceId, User $user): ?\App\Entity\Workspace
    {
        return $this->workspaceRepository->findOneForUser($workspaceId, $user);
    }

    private function resolveUserVisibleToast(User $user, int $toastId): ?Toast
    {
        if ($toastId <= 0) {
            return null;
        }

        $toast = $this->toastRepository->find($toastId);
        if (!$toast instanceof Toast) {
            return null;
        }

        $workspace = $this->workspaceRepository->findOneForUser($toast->getWorkspace()->getId(), $user);

        return $workspace instanceof Workspace ? $toast : null;
    }

    private function isTodoDigestSubject(?string $subject): bool
    {
        $normalized = mb_strtolower(trim((string) $subject));

        if (in_array($normalized, ['todo', 're: todo'], true)) {
            return true;
        }

        return str_contains($normalized, 'todo digest');
    }

    private function containsReplyCommandIntent(string $body): bool
    {
        return 1 === preg_match('/(#\d+\s+)?(assign|owner|due|comment|note|move|transfer|update|reword)\b/i', $body);
    }

    /**
     * @param array{action: string, payload: array<string, mixed>} $action
     *
     * @return list<array{label: string, url: string}>
     */
    private function buildConfirmationOptions(Toast $toast, User $actor, array $action): array
    {
        $options = [];
        $now = new \DateTimeImmutable();

        if ('assign' === $action['action']) {
            /** @var list<User> $candidateOwners */
            $candidateOwners = $action['payload']['candidate_owners'] ?? [];
            foreach ($candidateOwners as $owner) {
                $token = $this->jwtTokenService->createInboundActionConfirmToken(
                    $actor,
                    (int) $toast->getId(),
                    'assign',
                    ['ownerId' => $owner->getId()],
                    $now,
                );
                $options[] = [
                    'label' => sprintf('Assign to %s', $owner->getDisplayName()),
                    'url' => $this->appUrlGenerator->spaPath(sprintf('email/action/%s', $token)),
                ];
            }
        }

        if ('move' === $action['action']) {
            /** @var list<Workspace> $candidateWorkspaces */
            $candidateWorkspaces = $action['payload']['candidate_workspaces'] ?? [];
            foreach ($candidateWorkspaces as $workspace) {
                $token = $this->jwtTokenService->createInboundActionConfirmToken(
                    $actor,
                    (int) $toast->getId(),
                    'move',
                    ['workspaceId' => $workspace->getId()],
                    $now,
                );
                $options[] = [
                    'label' => sprintf('Move to %s', $workspace->getName()),
                    'url' => $this->appUrlGenerator->spaPath(sprintf('email/action/%s', $token)),
                ];
            }
        }

        return $options;
    }

    /**
     * @return array{ok: bool, message: string}
     */
    public function applyConfirmationToken(string $token): array
    {
        $payload = $this->jwtTokenService->decode($token);

        if (!is_array($payload) || 'inbound_action_confirm' !== ($payload['typ'] ?? null)) {
            return ['ok' => false, 'message' => 'This confirmation link is invalid or expired.'];
        }

        $userId = (int) ($payload['sub'] ?? 0);
        $toastId = (int) ($payload['tid'] ?? 0);
        $action = (string) ($payload['act'] ?? '');
        $actionPayload = is_array($payload['ap'] ?? null) ? $payload['ap'] : [];

        $user = $this->userRepository->find($userId);
        $toast = $this->entityManager->getRepository(Toast::class)->find($toastId);

        if (!$user instanceof User || !$toast instanceof Toast || $toast->getAuthor()->getId() !== $user->getId()) {
            return ['ok' => false, 'message' => 'This confirmation link cannot be applied anymore.'];
        }

        $normalizedAction = [
            'action' => $action,
            'status' => 'ready',
            'summary' => sprintf('confirmed %s', $action),
            'details' => 'Confirmed from email link.',
            'payload' => [],
        ];

        if ('assign' === $action) {
            $ownerId = (int) ($actionPayload['ownerId'] ?? 0);
            $owner = $this->workspaceWorkflow->findWorkspaceInviteeById($toast->getWorkspace(), $ownerId);
            $normalizedAction['payload']['owner'] = $owner;
        } elseif ('move' === $action) {
            $workspaceId = (int) ($actionPayload['workspaceId'] ?? 0);
            $workspace = $this->workspaceAccessWorkspaceOrNull($workspaceId, $user);
            $normalizedAction['payload']['workspace'] = $workspace;
        } else {
            return ['ok' => false, 'message' => 'Unsupported confirmation action.'];
        }

        $execution = $this->applyReplyAction($toast, $user, $normalizedAction, null);

        if (!$execution['applied']) {
            return ['ok' => false, 'message' => $execution['details']];
        }

        $this->entityManager->flush();

        return ['ok' => true, 'message' => sprintf('Task #%d updated: %s', $execution['toast']?->getId() ?? $toast->getId(), $execution['details'])];
    }

    /**
     * @param array{id: int, name: string, reason: string}|null $workspaceSuggestion
     *
     * @return list<array{action: string, status: string, summary: string, details: string, payload: array<string, mixed>}>
     */
    private function parseReplyActions(string $body, Toast $toast, User $actor, ?array $workspaceSuggestion): array
    {
        $segments = preg_split('/[\r\n;]+/', $body) ?: [];
        $actions = [];

        foreach ($segments as $segment) {
            $segment = trim($segment);
            if ('' === $segment) {
                continue;
            }

            if (str_starts_with($segment, '>')) {
                continue;
            }

            if (1 === preg_match('/^(on|le)\b.+wrote:?$/i', $segment)) {
                continue;
            }

            $taskReference = null;
            if (preg_match('/^#(\d+)\s+(.+)$/', $segment, $taskMatch)) {
                $taskReference = (int) $taskMatch[1];
                $segment = trim($taskMatch[2]);
            }

            if (null !== $taskReference && $taskReference !== $toast->getId()) {
                $actions[] = [
                    'action' => 'reference',
                    'status' => 'pending_confirmation',
                    'summary' => sprintf('Task #%d reference mismatch', $taskReference),
                    'details' => sprintf('This reply token currently points to task #%d. Please reference that task id.', $toast->getId()),
                    'payload' => [],
                ];
                continue;
            }

            if (preg_match('/^(?:assign|owner)(?:\s+to|\s+is)?\s+(.+)$/i', $segment, $matches)) {
                $target = trim($matches[1]);
                $resolution = $this->resolveAssignee($toast->getWorkspace(), $actor, $target);
                $actions[] = [
                    'action' => 'assign',
                    'status' => $resolution['confidence'] >= 0.8 ? 'ready' : 'pending_confirmation',
                    'summary' => sprintf('assign %s', $target),
                    'details' => $resolution['message'],
                    'payload' => [
                        'owner' => $resolution['owner'],
                        'candidate_owners' => $resolution['options'],
                    ],
                ];
                continue;
            }

            if (preg_match('/^due(?:\s+date)?\s*[:=]?\s*(\d{4}-\d{2}-\d{2})$/i', $segment, $matches)) {
                $dueOn = $matches[1];
                $actions[] = [
                    'action' => 'due',
                    'status' => 'ready',
                    'summary' => sprintf('due %s', $dueOn),
                    'details' => 'Due date command recognized with high confidence.',
                    'payload' => ['dueOn' => $dueOn],
                ];
                continue;
            }

            if (preg_match('/^(comment|note)\s*[:\-]?\s+(.+)$/i', $segment, $matches)) {
                $comment = trim($matches[2]);
                $actions[] = [
                    'action' => 'comment',
                    'status' => 'ready',
                    'summary' => sprintf('comment %s', $this->truncate($comment, 80)),
                    'details' => 'Comment command recognized with high confidence.',
                    'payload' => ['comment' => $comment],
                ];
                continue;
            }

            if (preg_match('/^(move|transfer)\s+(?:to\s+)?(.+)$/i', $segment, $matches)) {
                $targetName = trim($matches[2]);
                $resolution = $this->resolveWorkspaceTarget($actor, $targetName, $workspaceSuggestion, $toast);
                $actions[] = [
                    'action' => 'move',
                    'status' => $resolution['confidence'] >= 0.8 ? 'ready' : 'pending_confirmation',
                    'summary' => sprintf('move %s', $targetName),
                    'details' => $resolution['message'],
                    'payload' => [
                        'workspace' => $resolution['workspace'],
                        'candidate_workspaces' => $resolution['options'],
                    ],
                ];
                continue;
            }

            if (preg_match('/^(update|request\s+update|ask\s+for\s+update)$/i', $segment)) {
                $actions[] = [
                    'action' => 'update',
                    'status' => 'ready',
                    'summary' => 'request update',
                    'details' => 'Update request command recognized with high confidence.',
                    'payload' => [],
                ];
                continue;
            }

            if (preg_match('/^(reword|yes)$/i', $segment)) {
                $actions[] = [
                    'action' => 'reword',
                    'status' => 'ready',
                    'summary' => 'reword',
                    'details' => 'Reword command recognized with high confidence.',
                    'payload' => [],
                ];
                continue;
            }

            $actions[] = [
                'action' => 'unknown',
                'status' => 'pending_confirmation',
                'summary' => $this->truncate($segment, 80),
                'details' => 'Low-confidence instruction. Please reply with explicit commands: assign, due, comment/note, move, update, reword.',
                'payload' => [],
            ];
        }

        return $actions;
    }

    /**
     * @param array{id: int, name: string, reason: string}|null $workspaceSuggestion
     * @param array{action: string, status: string, summary: string, details: string, payload: array<string, mixed>} $action
     *
     * @return array{applied: bool, details: string, toast: ?Toast}
     */
    private function applyReplyAction(Toast $toast, User $actor, array $action, ?array $workspaceSuggestion): array
    {
        switch ($action['action']) {
            case 'assign':
                $owner = $action['payload']['owner'] ?? null;
                if (!$owner instanceof User) {
                    return ['applied' => false, 'details' => 'Could not resolve assignee.', 'toast' => null];
                }

                $toast->setOwner($owner);

                return ['applied' => true, 'details' => sprintf('Assigned to %s.', $owner->getDisplayName()), 'toast' => null];

            case 'due':
                $dueOn = (string) ($action['payload']['dueOn'] ?? '');
                try {
                    $dueAt = new \DateTimeImmutable($dueOn);
                } catch (\Exception) {
                    return ['applied' => false, 'details' => 'Invalid due date format. Use YYYY-MM-DD.', 'toast' => null];
                }

                $toast->setDueAt($dueAt);

                return ['applied' => true, 'details' => sprintf('Due date set to %s.', $dueAt->format('Y-m-d')), 'toast' => null];

            case 'comment':
                $commentText = trim((string) ($action['payload']['comment'] ?? ''));
                if ('' === $commentText) {
                    return ['applied' => false, 'details' => 'Comment cannot be empty.', 'toast' => null];
                }

                $comment = (new ToastComment())
                    ->setToast($toast)
                    ->setAuthor($actor)
                    ->setContent($commentText);
                $this->entityManager->persist($comment);
                $toast->addComment($comment);

                return ['applied' => true, 'details' => 'Comment added.', 'toast' => null];

            case 'move':
                $targetWorkspace = $action['payload']['workspace'] ?? null;
                if (!$targetWorkspace instanceof Workspace) {
                    return ['applied' => false, 'details' => 'Could not resolve target workspace.', 'toast' => null];
                }

                if ($targetWorkspace->getId() === $toast->getWorkspace()->getId()) {
                    return ['applied' => false, 'details' => 'Toast is already in that workspace.', 'toast' => null];
                }

                $transferredToast = $this->toastTransfer->transfer($toast, $targetWorkspace, $actor);

                return ['applied' => true, 'details' => sprintf('Moved to %s.', $targetWorkspace->getName()), 'toast' => $transferredToast];

            case 'update':
                $owner = $toast->getOwner();
                if (!$owner instanceof User) {
                    return ['applied' => false, 'details' => 'No assignee to request an update from.', 'toast' => null];
                }

                $requestComment = (new ToastComment())
                    ->setToast($toast)
                    ->setAuthor($actor)
                    ->setContent(sprintf('Update requested from %s by %s.', $owner->getDisplayName(), $actor->getDisplayName()));
                $this->entityManager->persist($requestComment);
                $toast->addComment($requestComment);

                return ['applied' => true, 'details' => sprintf('Update requested from %s.', $owner->getDisplayName()), 'toast' => null];

            case 'reword':
                $proposal = $this->toastDraftRefinement->refine(
                    $toast->getWorkspace(),
                    $toast->getTitle(),
                    $toast->getDescription(),
                    $actor,
                );
                $this->applyRewordProposal($toast, $proposal);

                return ['applied' => true, 'details' => 'Title/description reworded.', 'toast' => null];

            default:
                if (null !== $workspaceSuggestion) {
                    return ['applied' => false, 'details' => sprintf('Suggested workspace remains %s.', $workspaceSuggestion['name']), 'toast' => null];
                }

                return ['applied' => false, 'details' => 'No action applied.', 'toast' => null];
        }
    }

    /**
     * @return array{owner: ?User, confidence: float, message: string, options: list<User>}
     */
    private function resolveAssignee(Workspace $workspace, User $actor, string $target): array
    {
        $target = trim($target);
        if ('' === $target) {
            return ['owner' => null, 'confidence' => 0.3, 'message' => 'Assignee target missing.', 'options' => []];
        }

        if (in_array(mb_strtolower($target), ['me', 'myself'], true)) {
            return ['owner' => $actor, 'confidence' => 1.0, 'message' => 'Resolved to current user.', 'options' => []];
        }

        $invitees = $this->workspaceWorkflow->getWorkspaceInvitees($workspace);

        foreach ($invitees as $invitee) {
            if (0 === strcasecmp($invitee->getEmail(), $target)) {
                return ['owner' => $invitee, 'confidence' => 1.0, 'message' => sprintf('Resolved by exact email: %s.', $invitee->getDisplayName()), 'options' => []];
            }
        }

        foreach ($invitees as $invitee) {
            if (0 === strcasecmp($invitee->getDisplayName(), $target)) {
                return ['owner' => $invitee, 'confidence' => 0.9, 'message' => sprintf('Resolved by display name: %s.', $invitee->getDisplayName()), 'options' => []];
            }
        }

        $matches = array_values(array_filter(
            $invitees,
            static fn (User $invitee): bool => str_contains(mb_strtolower($invitee->getDisplayName()), mb_strtolower($target))
                || str_contains(mb_strtolower($invitee->getEmail()), mb_strtolower($target)),
        ));

        if ([] !== $matches) {
            return [
                'owner' => null,
                'confidence' => 0.5,
                'message' => 'Assignee partially matched. Please confirm one option.',
                'options' => array_slice($matches, 0, 4),
            ];
        }

        return ['owner' => null, 'confidence' => 0.4, 'message' => 'Assignee not found in this workspace.', 'options' => []];
    }

    /**
     * @param array{id: int, name: string, reason: string}|null $workspaceSuggestion
     *
     * @return array{workspace: ?Workspace, confidence: float, message: string, options: list<Workspace>}
     */
    private function resolveWorkspaceTarget(User $actor, string $targetName, ?array $workspaceSuggestion, Toast $toast): array
    {
        $targetName = trim($targetName);
        if ('' === $targetName) {
            return ['workspace' => null, 'confidence' => 0.3, 'message' => 'Target workspace missing.', 'options' => []];
        }

        if (in_array(mb_strtolower($targetName), ['suggested', 'suggestion', 'recommended'], true) && null !== $workspaceSuggestion) {
            $workspace = $this->workspaceAccessWorkspaceOrNull((int) $workspaceSuggestion['id'], $actor);
            if ($workspace instanceof Workspace) {
                return ['workspace' => $workspace, 'confidence' => 0.95, 'message' => sprintf('Resolved using suggested workspace: %s.', $workspace->getName()), 'options' => []];
            }
        }

        $candidates = $this->workspaceRepository->findForUser($actor);

        foreach ($candidates as $candidate) {
            if ($candidate->getId() === $toast->getWorkspace()->getId()) {
                continue;
            }

            if (0 === strcasecmp($candidate->getName(), $targetName)) {
                return ['workspace' => $candidate, 'confidence' => 1.0, 'message' => sprintf('Resolved by exact workspace name: %s.', $candidate->getName()), 'options' => []];
            }
        }

        $partialMatches = [];
        foreach ($candidates as $candidate) {
            if ($candidate->getId() === $toast->getWorkspace()->getId()) {
                continue;
            }

            if (str_contains(mb_strtolower($candidate->getName()), mb_strtolower($targetName))) {
                $partialMatches[] = $candidate;
            }
        }

        if ([] !== $partialMatches) {
            return [
                'workspace' => null,
                'confidence' => 0.6,
                'message' => 'Workspace partially matched. Please confirm one option.',
                'options' => array_slice($partialMatches, 0, 4),
            ];
        }

        return ['workspace' => null, 'confidence' => 0.3, 'message' => 'Target workspace not found.', 'options' => []];
    }

    /**
     * @param array{title: string, description: string, ownerId: ?int, dueOn: ?string} $proposal
     */
    private function applyRewordProposal(Toast $toast, array $proposal): void
    {
        $toast
            ->setTitle(trim($proposal['title']))
            ->setDescription(trim((string) $proposal['description']) ?: null)
            ->setOwner($this->resolveWorkspaceOwner($toast->getWorkspace(), $proposal['ownerId']))
            ->setDueAt($this->resolveDueAt($proposal['dueOn']));
    }

    private function resolveWorkspaceOwner(Workspace $workspace, ?int $ownerId): ?User
    {
        if (null === $ownerId) {
            return null;
        }

        return $this->workspaceWorkflow->findWorkspaceInviteeById($workspace, $ownerId);
    }

    private function resolveDueAt(?string $dueOn): ?\DateTimeImmutable
    {
        $dueOn = trim((string) $dueOn);
        if ('' === $dueOn) {
            return null;
        }

        return new \DateTimeImmutable($dueOn);
    }

    /**
     * @param array{id: int, name: string, reason: string}|null $workspaceSuggestion
     */
    private function applyAutomaticWorkspaceSuggestion(Toast $toast, User $actor, ?array $workspaceSuggestion): Toast
    {
        if (!$actor->isInboundAutoApplyWorkspace()) {
            return $toast;
        }

        if (null === $workspaceSuggestion) {
            return $toast;
        }

        $targetWorkspace = $this->workspaceAccessWorkspaceOrNull((int) $workspaceSuggestion['id'], $actor);
        if (!$targetWorkspace instanceof Workspace || $targetWorkspace->getId() === $toast->getWorkspace()->getId()) {
            return $toast;
        }

        return $this->toastTransfer->transfer($toast, $targetWorkspace, $actor);
    }

    private function applyAutomaticRefinementSuggestions(Workspace $workspace, Toast $toast, User $actor): bool
    {
        $applyReword = $actor->isInboundAutoApplyReword();
        $applyAssignee = $actor->isInboundAutoApplyAssignee();
        $applyDueDate = $actor->isInboundAutoApplyDueDate();

        if (!$applyReword && !$applyAssignee && !$applyDueDate) {
            return false;
        }

        try {
            $proposal = $this->toastDraftRefinement->refine(
                $workspace,
                $toast->getTitle(),
                $toast->getDescription(),
                $actor,
            );
        } catch (SessionSummaryUnavailableException) {
            return false;
        }

        if ($applyReword) {
            $toast
                ->setTitle(trim($proposal['title']))
                ->setDescription(trim((string) $proposal['description']) ?: null);
        }

        if ($applyAssignee) {
            $toast->setOwner($this->resolveWorkspaceOwner($toast->getWorkspace(), $proposal['ownerId']));
        }

        if ($applyDueDate) {
            $toast->setDueAt($this->resolveDueAt($proposal['dueOn']));
        }

        return $applyReword;
    }

    private function buildToastReplyAddress(User $user, Toast $toast): ?string
    {
        $replyToken = $this->toastReplyToken->issue($user, $toast, ToastReplyToken::ACTION_REPHRASE);

        return $this->inboundReplyAddress->buildAddress($replyToken->token->getSelector(), $replyToken->plainToken);
    }
}
