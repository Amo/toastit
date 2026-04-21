<?php

namespace App\Workspace;

use App\Entity\Toast;
use App\Entity\ToastingSession;
use App\Entity\User;
use App\Entity\Workspace;
use App\Entity\WorkspaceNote;
use App\Entity\WorkspaceNoteVersion;
use App\Repository\WorkspaceRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class WorkspaceAccessService
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

    public function assertRoot(): void
    {
        if (!$this->getUserOrFail()->isRoot()) {
            throw new AccessDeniedHttpException();
        }
    }

    public function assertRouteOrRoot(): void
    {
        $user = $this->getUserOrFail();

        if (!$user->isRoot() && !$user->isRoute()) {
            throw new AccessDeniedHttpException();
        }
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

    public function getToastingSessionOrFail(Workspace $workspace, int $sessionId): ToastingSession
    {
        $session = $this->entityManager->getRepository(ToastingSession::class)->find($sessionId);

        if (!$session instanceof ToastingSession || $session->getWorkspace()->getId() !== $workspace->getId()) {
            throw new NotFoundHttpException();
        }

        return $session;
    }

    public function getWorkspaceNoteOrFail(Workspace $workspace, int $noteId): WorkspaceNote
    {
        $note = $this->entityManager->getRepository(WorkspaceNote::class)->find($noteId);

        if (!$note instanceof WorkspaceNote || $note->getWorkspace()->getId() !== $workspace->getId()) {
            throw new NotFoundHttpException();
        }

        return $note;
    }

    public function getWorkspaceNoteVersionOrFail(WorkspaceNote $note, int $versionId): WorkspaceNoteVersion
    {
        $version = $this->entityManager->getRepository(WorkspaceNoteVersion::class)->find($versionId);

        if (!$version instanceof WorkspaceNoteVersion || $version->getNote()->getId() !== $note->getId()) {
            throw new NotFoundHttpException();
        }

        return $version;
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
