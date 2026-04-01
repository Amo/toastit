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
            'workspaces' => array_map(fn (Workspace $workspace): array => $this->buildWorkspaceSummary($workspace), $this->workspaceRepository->findForUser($user)),
        ];
    }

    private function buildWorkspaceSummary(Workspace $workspace): array
    {
        $openItems = 0;
        $resolvedItems = 0;

        foreach ($workspace->getItems() as $item) {
            if (\App\Entity\Toast::DISCUSSION_TREATED === $item->getDiscussionStatus()) {
                ++$resolvedItems;
                continue;
            }

            ++$openItems;
        }

        return [
            'id' => $workspace->getId(),
            'name' => $workspace->getName(),
            'isDefault' => $workspace->isDefault(),
            'meetingMode' => $workspace->getMeetingMode(),
            'meetingStartedAt' => $workspace->getMeetingStartedAt()?->format(\DateTimeInterface::ATOM),
            'memberCount' => $workspace->getMemberships()->count(),
            'openItemCount' => $openItems,
            'resolvedItemCount' => $resolvedItems,
        ];
    }
}
