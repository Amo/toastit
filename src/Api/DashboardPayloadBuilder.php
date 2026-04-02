<?php

namespace App\Api;

use App\Entity\User;
use App\Entity\Workspace;
use App\Repository\WorkspaceRepository;

final class DashboardPayloadBuilder
{
    public function __construct(
        private readonly WorkspaceRepository $workspaceRepository,
    ) {
    }

    public function build(User $user): array
    {
        return [
            'workspaces' => array_map(fn (Workspace $workspace): array => $this->buildWorkspaceSummary($workspace, $user), $this->workspaceRepository->findForUser($user)),
        ];
    }

    private function buildWorkspaceSummary(Workspace $workspace, User $currentUser): array
    {
        $openItems = 0;
        $resolvedItems = 0;
        $assignedOpenItems = 0;
        $lateOpenItems = 0;
        $today = new \DateTimeImmutable('today');

        foreach ($workspace->getItems() as $item) {
            if (\App\Entity\Toast::DISCUSSION_TREATED === $item->getDiscussionStatus()) {
                ++$resolvedItems;
                continue;
            }

            if ($item->isVetoed()) {
                continue;
            }

            ++$openItems;

            if (($item->getOwner()?->getId()) === $currentUser->getId()) {
                ++$assignedOpenItems;
            }

            if (null !== $item->getDueAt() && $item->getDueAt() < $today) {
                ++$lateOpenItems;
            }
        }

        return [
            'id' => $workspace->getId(),
            'name' => $workspace->getName(),
            'isDefault' => $workspace->isDefault(),
            'isSoloWorkspace' => $workspace->isSoloWorkspace(),
            'meetingMode' => $workspace->getMeetingMode(),
            'meetingStartedAt' => $workspace->getMeetingStartedAt()?->format(\DateTimeInterface::ATOM),
            'memberCount' => $workspace->getMemberships()->count(),
            'openItemCount' => $openItems,
            'resolvedItemCount' => $resolvedItems,
            'assignedOpenItemCount' => $assignedOpenItems,
            'lateOpenItemCount' => $lateOpenItems,
            'membersPreview' => array_slice(array_map(
                static fn (User $member): array => [
                    'id' => $member->getId(),
                    'displayName' => $member->getDisplayName(),
                    'email' => $member->getEmail(),
                    'initials' => $member->getInitials(),
                    'gravatarUrl' => $member->getGravatarUrl(),
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
