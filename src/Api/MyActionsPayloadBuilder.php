<?php

namespace App\Api;

use App\Entity\Toast;
use App\Entity\User;
use App\Profile\AvatarUrlService;
use App\Profile\UserDateTimeFormatter;
use App\Repository\ToastRepository;
use App\Workspace\AssignedToastPriorityService;

final class MyActionsPayloadBuilder
{
    public function __construct(
        private readonly ToastRepository $toastRepository,
        private readonly AssignedToastPriorityService $assignedToastPriority,
        private readonly AvatarUrlService $avatarUrl,
        private readonly UserDateTimeFormatter $userDateTimeFormatter,
    ) {
    }

    public function build(User $user): array
    {
        $today = new \DateTimeImmutable('today');
        $actions = $this->assignedToastPriority->sort($this->toastRepository->findAssignedActiveForUser($user, 200));
        $lateCount = 0;
        $dueSoonCount = 0;
        $workspaceIds = [];

        foreach ($actions as $action) {
            $workspaceIds[$action->getWorkspace()->getId() ?? spl_object_id($action->getWorkspace())] = true;

            if (null === $action->getDueAt()) {
                continue;
            }

            if ($action->getDueAt() < $today) {
                ++$lateCount;
                continue;
            }

            if ($action->getDueAt() <= $today->modify('+7 days')) {
                ++$dueSoonCount;
            }
        }

        return [
            'currentUser' => [
                'id' => $user->getId(),
                'displayName' => $user->getDisplayName(),
                'email' => $user->getPublicEmail(),
                'initials' => $user->getInitials(),
                'gravatarUrl' => $this->avatarUrl->resolve($user),
            ],
            'summary' => [
                'assignedCount' => count($actions),
                'lateCount' => $lateCount,
                'dueSoonCount' => $dueSoonCount,
                'workspaceCount' => count($workspaceIds),
            ],
            'actions' => array_map(
                fn (Toast $action): array => $this->buildActionPayload($action, $today, $user),
                $actions,
            ),
        ];
    }

    private function buildActionPayload(Toast $action, \DateTimeImmutable $today, User $user): array
    {
        $dueAt = $action->getDueAt();
        $isLate = null !== $dueAt && $dueAt < $today;
        $isDueSoon = null !== $dueAt && !$isLate && $dueAt <= $today->modify('+7 days');

        return [
            'id' => $action->getId(),
            'title' => $action->getTitle(),
            'description' => $action->getDescription(),
            'voteCount' => $action->getVoteCount(),
            'isBoosted' => $action->isBoosted(),
            'isLate' => $isLate,
            'isDueSoon' => $isDueSoon,
            'dueOn' => $dueAt?->format('Y-m-d'),
            'dueOnDisplay' => $this->userDateTimeFormatter->formatDate($dueAt, $user),
            'createdAt' => $action->getCreatedAt()->format(\DateTimeInterface::ATOM),
            'createdAtDisplay' => $this->userDateTimeFormatter->formatDateTime($action->getCreatedAt(), $user),
            'commentsCount' => $action->getComments()->count(),
            'workspace' => [
                'id' => $action->getWorkspace()->getId(),
                'name' => $action->getWorkspace()->getName(),
                'isSoloWorkspace' => $action->getWorkspace()->isSoloWorkspace(),
                'isInboxWorkspace' => $action->getWorkspace()->isInboxWorkspace(),
            ],
            'author' => [
                'id' => $action->getAuthor()->getId(),
                'displayName' => $action->getAuthor()->getDisplayName(),
                'initials' => $action->getAuthor()->getInitials(),
                'gravatarUrl' => $this->avatarUrl->resolve($action->getAuthor()),
            ],
            'owner' => $action->getOwner() ? [
                'id' => $action->getOwner()->getId(),
                'displayName' => $action->getOwner()->getDisplayName(),
                'initials' => $action->getOwner()->getInitials(),
                'gravatarUrl' => $this->avatarUrl->resolve($action->getOwner()),
            ] : null,
        ];
    }
}
