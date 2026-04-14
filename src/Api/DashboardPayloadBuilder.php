<?php

namespace App\Api;

use App\Entity\User;
use App\Entity\Workspace;
use App\Profile\AvatarUrlService;
use App\Repository\WorkspaceRepository;

final class DashboardPayloadBuilder
{
    public function __construct(
        private readonly WorkspaceRepository $workspaceRepository,
        private readonly AvatarUrlService $avatarUrl,
        private readonly MyActionsPayloadBuilder $myActionsPayloadBuilder,
    ) {
    }

    public function build(User $user): array
    {
        $myActions = $this->myActionsPayloadBuilder->build($user);
        $inboxWorkspace = $this->workspaceRepository->findInboxWorkspaceForUser($user);
        $workspaces = $this->workspaceRepository->findForUser($user);

        if ($inboxWorkspace instanceof Workspace) {
            array_unshift($workspaces, $inboxWorkspace);
        }

        return [
            'myActions' => [
                'summary' => $myActions['summary'],
                'actions' => $myActions['actions'],
            ],
            'workspaces' => array_map(fn (Workspace $workspace): array => $this->buildWorkspaceSummary($workspace, $user), $workspaces),
        ];
    }

    private function buildWorkspaceSummary(Workspace $workspace, User $currentUser): array
    {
        $openItems = 0;
        $resolvedItems = 0;
        $assignedOpenItems = 0;
        $assignedLateOpenItems = 0;
        $lateOpenItems = 0;
        $today = new \DateTimeImmutable('today');

        foreach ($workspace->getItems() as $item) {
            if (\App\Entity\Toast::STATUS_TOASTED === $item->getStatus()) {
                ++$resolvedItems;
                continue;
            }

            if ($item->isVetoed()) {
                continue;
            }

            ++$openItems;

            $isAssignedToCurrentUser = ($item->getOwner()?->getId()) === $currentUser->getId();

            if ($isAssignedToCurrentUser) {
                ++$assignedOpenItems;
            }

            if (null !== $item->getDueAt() && $item->getDueAt() < $today) {
                ++$lateOpenItems;

                if ($isAssignedToCurrentUser) {
                    ++$assignedLateOpenItems;
                }
            }
        }

        return [
            'id' => $workspace->getId(),
            'name' => $workspace->getName(),
            'ownerDisplayName' => $workspace->getOrganizer()->getDisplayName(),
            'isInboxWorkspace' => $workspace->isInboxWorkspace(),
            'isDefault' => $workspace->isDefault(),
            'isSoloWorkspace' => $workspace->isSoloWorkspace(),
            'currentUserIsOwner' => $workspace->isOwnedBy($currentUser),
            'meetingMode' => $workspace->getMeetingMode(),
            'meetingStartedAt' => $workspace->getMeetingStartedAt()?->format(\DateTimeInterface::ATOM),
            'memberCount' => $workspace->getMemberships()->count(),
            'openItemCount' => $openItems,
            'resolvedItemCount' => $resolvedItems,
            'assignedOpenItemCount' => $assignedOpenItems,
            'assignedLateOpenItemCount' => $assignedLateOpenItems,
            'lateOpenItemCount' => $lateOpenItems,
            'membersPreview' => array_slice(array_map(
                fn (User $member): array => [
                    'id' => $member->getId(),
                    'displayName' => $member->getDisplayName(),
                    'email' => $member->getEmail(),
                    'initials' => $member->getInitials(),
                    'gravatarUrl' => $this->avatarUrl->resolve($member),
                ],
                $this->buildWorkspaceMembers($workspace)
            ), 0, 7),
        ];
    }

    /**
     * @return list<User>
     */
    private function buildWorkspaceMembers(Workspace $workspace): array
    {
        $members = [$workspace->getOrganizer()];

        foreach ($workspace->getMemberships() as $membership) {
            $members[] = $membership->getUser();
        }

        $membersById = [];

        foreach ($members as $member) {
            $membersById[$member->getId() ?? spl_object_id($member)] = $member;
        }

        $members = array_values($membersById);
        usort($members, static fn (User $left, User $right): int => strcmp($left->getDisplayName(), $right->getDisplayName()));

        return $members;
    }
}
