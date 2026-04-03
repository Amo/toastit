<?php

namespace App\Controller\Api\Workspace;

use App\Workspace\WorkspaceAccessService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

final class DeleteController extends AbstractController
{
    public function __construct(
        private readonly WorkspaceAccessService $workspaceAccess,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    #[Route('/api/workspaces/{id}', name: 'api_workspace_delete', methods: ['DELETE'])]
    public function __invoke(int $id): JsonResponse
    {
        $workspace = $this->workspaceAccess->getWorkspaceOrFail($id);
        $this->workspaceAccess->assertOwner($workspace);

        if ($workspace->isInboxWorkspace()) {
            return $this->json(['ok' => false, 'error' => 'inbox_workspace_not_deletable'], 400);
        }

        $workspace->softDelete();
        $this->entityManager->flush();

        return $this->json(['ok' => true]);
    }
}
