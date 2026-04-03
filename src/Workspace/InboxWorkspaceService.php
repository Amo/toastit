<?php

namespace App\Workspace;

use App\Entity\User;
use App\Entity\Workspace;
use App\Entity\WorkspaceMember;
use App\Repository\WorkspaceMemberRepository;
use App\Repository\WorkspaceRepository;
use Doctrine\ORM\EntityManagerInterface;

final class InboxWorkspaceService
{
    public function __construct(
        private readonly WorkspaceRepository $workspaceRepository,
        private readonly WorkspaceMemberRepository $workspaceMemberRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function getOrCreateInboxWorkspace(User $user): Workspace
    {
        $workspace = $this->workspaceRepository->findInboxWorkspaceForUser($user);

        if ($workspace instanceof Workspace) {
            return $workspace;
        }

        $workspace = (new Workspace())
            ->setName('Inbox')
            ->setOrganizer($user)
            ->setIsInboxWorkspace(true)
            ->setIsSoloWorkspace(true);

        $membership = (new WorkspaceMember())
            ->setUser($user)
            ->setDisplayOrder($this->workspaceMemberRepository->nextDisplayOrderForUser($user))
            ->setIsOwner(true);

        $workspace->addMembership($membership);

        $this->entityManager->persist($workspace);
        $this->entityManager->persist($membership);

        return $workspace;
    }
}
