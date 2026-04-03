<?php

namespace App\Workspace;

use App\Entity\Toast;
use App\Entity\User;
use App\Entity\Workspace;
use Doctrine\ORM\EntityManagerInterface;

final class ToastTransferService
{
    public function __construct(
        private readonly WorkspaceWorkflowService $workspaceWorkflow,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function transfer(Toast $toast, Workspace $targetWorkspace, User $actor): Toast
    {
        $owner = $toast->getOwner();

        if ($owner && null === $this->workspaceWorkflow->findWorkspaceInviteeById($targetWorkspace, $owner->getId() ?? 0)) {
            $owner = null;
        }

        $transferredToast = (new Toast())
            ->setWorkspace($targetWorkspace)
            ->setAuthor($actor)
            ->setTitle($toast->getTitle())
            ->setDescription($toast->getDescription())
            ->setStatus(Toast::STATUS_OPEN)
            ->setDiscussionStatus(Toast::DISCUSSION_PENDING)
            ->setDiscussionNotes(null)
            ->setIsBoosted(false)
            ->setOwner($owner)
            ->setDueAt($toast->getDueAt())
            ->setStatusChangedAt(null);

        $this->entityManager->persist($transferredToast);
        $this->entityManager->remove($toast);

        return $transferredToast;
    }
}
