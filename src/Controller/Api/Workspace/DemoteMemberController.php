<?php

namespace App\Controller\Api\Workspace;

use App\Entity\WorkspaceMember;
use App\Workspace\WorkspaceAccess;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

final class DemoteMemberController extends AbstractController
{
    public function __construct(
        private readonly WorkspaceAccess $workspaceAccess,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    #[Route('/api/workspaces/{workspaceId}/members/{memberId}/demote', name: 'api_workspace_member_demote', methods: ['POST'])]
    public function __invoke(int $workspaceId, int $memberId): JsonResponse
    {
        $workspace = $this->workspaceAccess->getWorkspaceOrFail($workspaceId);
        $this->workspaceAccess->assertOwner($workspace);
        $membership = $this->entityManager->getRepository(WorkspaceMember::class)->find($memberId);

        if (!$membership instanceof WorkspaceMember || $membership->getWorkspace()->getId() !== $workspace->getId()) {
            return $this->json(['ok' => false, 'error' => 'member_not_found'], 404);
        }

        if ($membership->isOwner() && $workspace->getOwnerCount() <= 1) {
            return $this->json(['ok' => false, 'error' => 'last_owner'], 400);
        }

        $membership->setIsOwner(false);
        $this->entityManager->flush();

        return $this->json(['ok' => true]);
    }
}
