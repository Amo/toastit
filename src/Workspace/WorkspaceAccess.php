<?php

namespace App\Workspace;

use App\Entity\Toast;
use App\Entity\User;
use App\Entity\Workspace;
use App\Repository\WorkspaceRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class WorkspaceAccess
{
    public function __construct(
        private readonly WorkspaceRepository $workspaceRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly Security $security,
    ) {
    }

    public function getUserOrFail(): User
    {
        $user = $this->security->getUser();

        if (!$user instanceof User) {
            throw new AccessDeniedHttpException();
        }

        return $user;
    }

    public function getWorkspaceOrFail(int $workspaceId): Workspace
    {
        $workspace = $this->workspaceRepository->findOneForUser($workspaceId, $this->getUserOrFail());

        if (!$workspace instanceof Workspace) {
            throw new NotFoundHttpException();
        }

        return $workspace;
    }

    public function getItemOrFail(int $itemId): Toast
    {
        $item = $this->entityManager->getRepository(Toast::class)->find($itemId);

        if (!$item instanceof Toast) {
            throw new NotFoundHttpException();
        }

        $this->getWorkspaceOrFail($item->getWorkspace()->getId());

        return $item;
    }

    public function assertOwner(Workspace $workspace): void
    {
        if (!$workspace->isOwnedBy($this->getUserOrFail())) {
            throw new AccessDeniedHttpException();
        }
    }

    public function assertMeetingModeActive(Workspace $workspace): void
    {
        if (!$workspace->isMeetingLive()) {
            throw new AccessDeniedHttpException();
        }
    }

    public function assertMeetingModeIdle(Workspace $workspace): void
    {
        if ($workspace->isMeetingLive()) {
            throw new AccessDeniedHttpException();
        }
    }
}
