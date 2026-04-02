<?php

namespace App\Controller\Api\Workspace;

use App\Repository\WorkspaceRepository;
use App\Workspace\WorkspaceAccessService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

final class RestoreController extends AbstractController
{
    public function __construct(
        private readonly WorkspaceAccessService $workspaceAccess,
        private readonly WorkspaceRepository $workspaceRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    #[Route('/api/workspaces/{id}/restore', name: 'api_workspace_restore', methods: ['POST'])]
    public function __invoke(int $id): JsonResponse
    {
        $workspace = $this->workspaceRepository->findOneDeletedOwnedByUser($id, $this->workspaceAccess->getUserOrFail());

        if (!$workspace) {
            throw $this->createNotFoundException();
        }

        $workspace->restore();
        $this->entityManager->flush();

        return $this->json(['ok' => true]);
    }
}
