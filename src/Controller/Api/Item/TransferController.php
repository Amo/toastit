<?php

namespace App\Controller\Api\Item;

use App\Entity\Toast;
use App\Entity\Workspace;
use App\Entity\User;
use App\Workspace\WorkspaceAccess;
use App\Workspace\WorkspaceWorkflow;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

final class TransferController extends AbstractController
{
    public function __construct(
        private readonly WorkspaceAccess $workspaceAccess,
        private readonly WorkspaceWorkflow $workspaceWorkflow,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    #[Route('/api/items/{id}/transfer', name: 'api_item_transfer', methods: ['POST'])]
    public function __invoke(int $id, Request $request): JsonResponse
    {
        $source = $this->workspaceAccess->getItemOrFail($id);
        $sourceWorkspace = $source->getWorkspace();
        $this->workspaceAccess->assertOwner($sourceWorkspace);

        if (!$source->isNew()) {
            return $this->json(['ok' => false, 'error' => 'only_new_toasts_can_be_transferred'], 400);
        }

        $payload = $request->toArray();
        $targetWorkspaceId = is_numeric($payload['targetWorkspaceId'] ?? null) ? (int) $payload['targetWorkspaceId'] : 0;

        if ($targetWorkspaceId <= 0 || $targetWorkspaceId === $sourceWorkspace->getId()) {
            return $this->json(['ok' => false, 'error' => 'invalid_target_workspace'], 400);
        }

        $targetWorkspace = $this->workspaceAccess->getWorkspaceOrFail($targetWorkspaceId);
        $transferredToast = $this->duplicateToast($source, $targetWorkspace, $this->workspaceAccess->getUserOrFail());

        $this->entityManager->persist($transferredToast);
        $this->entityManager->remove($source);
        $this->entityManager->flush();

        return $this->json([
            'ok' => true,
            'toastId' => $transferredToast->getId(),
            'workspaceId' => $targetWorkspace->getId(),
        ]);
    }

    private function duplicateToast(Toast $source, Workspace $targetWorkspace, User $author): Toast
    {
        $owner = $source->getOwner();

        if ($owner && null === $this->workspaceWorkflow->findWorkspaceInviteeById($targetWorkspace, $owner->getId() ?? 0)) {
            $owner = null;
        }

        return (new Toast())
            ->setWorkspace($targetWorkspace)
            ->setAuthor($author)
            ->setTitle($source->getTitle())
            ->setDescription($source->getDescription())
            ->setStatus(Toast::STATUS_OPEN)
            ->setDiscussionStatus(Toast::DISCUSSION_PENDING)
            ->setDiscussionNotes(null)
            ->setIsBoosted(false)
            ->setOwner($owner)
            ->setDueAt($source->getDueAt())
            ->setStatusChangedAt(null);
    }
}
