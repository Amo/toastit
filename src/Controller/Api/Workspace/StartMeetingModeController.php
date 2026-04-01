<?php

namespace App\Controller\Api\Workspace;

use App\Workspace\WorkspaceAccess;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

final class StartMeetingModeController extends AbstractController
{
    public function __construct(
        private readonly WorkspaceAccess $workspaceAccess,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    #[Route('/api/workspaces/{id}/meeting/start', name: 'api_workspace_meeting_start', methods: ['POST'])]
    public function __invoke(int $id): JsonResponse
    {
        $workspace = $this->workspaceAccess->getWorkspaceOrFail($id);
        $this->workspaceAccess->assertOwner($workspace);

        if ($workspace->isSoloWorkspace()) {
            throw new AccessDeniedHttpException();
        }

        $workspace->startMeetingMode($this->workspaceAccess->getUserOrFail());
        $this->entityManager->flush();

        return $this->json(['ok' => true]);
    }
}
