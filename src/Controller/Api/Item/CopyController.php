<?php

namespace App\Controller\Api\Item;

use App\Entity\Toast;
use App\Entity\Workspace;
use App\Entity\User;
use App\Workspace\WorkspaceAccessService;
use App\Workspace\WorkspaceWorkflowService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

final class CopyController extends AbstractController
{
    public function __construct(
        private readonly WorkspaceAccessService $workspaceAccess,
        private readonly WorkspaceWorkflowService $workspaceWorkflow,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    #[Route('/api/items/{id}/copy', name: 'api_item_copy', methods: ['POST'])]
    public function __invoke(int $id, Request $request): JsonResponse
    {
        $source = $this->workspaceAccess->getItemOrFail($id);
        $payload = $request->toArray();
        $targetWorkspaceId = is_numeric($payload['targetWorkspaceId'] ?? null)
            ? (int) $payload['targetWorkspaceId']
            : $source->getWorkspace()->getId();

        $targetWorkspace = $this->workspaceAccess->getWorkspaceOrFail($targetWorkspaceId);
        $copiedToast = $this->duplicateToast($source, $targetWorkspace, $this->workspaceAccess->getUserOrFail());

        $this->entityManager->persist($copiedToast);
        $this->entityManager->flush();

        return $this->json([
            'ok' => true,
            'toastId' => $copiedToast->getId(),
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
