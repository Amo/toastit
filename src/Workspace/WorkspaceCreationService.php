<?php

namespace App\Workspace;

use App\Entity\User;
use App\Entity\Workspace;
use App\Entity\WorkspaceMember;
use App\Repository\WorkspaceMemberRepository;
use Doctrine\ORM\EntityManagerInterface;

final class WorkspaceCreationService
{
    public function __construct(
        private readonly WorkspaceMemberRepository $workspaceMemberRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function createOwnedWorkspace(User $user, string $name): Workspace
    {
        $workspace = (new Workspace())
            ->setName($name)
            ->setOrganizer($user);

        $membership = (new WorkspaceMember())
            ->setUser($user)
            ->setDisplayOrder($this->workspaceMemberRepository->nextDisplayOrderForUser($user))
            ->setIsOwner(true);

        $workspace->addMembership($membership);

        $this->entityManager->persist($workspace);
        $this->entityManager->persist($membership);

        return $workspace;
    }

    public function flush(): void
    {
        $this->entityManager->flush();
    }
}
