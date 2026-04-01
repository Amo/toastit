<?php

namespace App\Controller\Api\Workspace;

use App\Workspace\WorkspaceAccess;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

final class StopMeetingModeController extends AbstractController
{
    public function __construct(
        private readonly WorkspaceAccess $workspaceAccess,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    #[Route('/api/workspaces/{id}/meeting/stop', name: 'api_workspace_meeting_stop', methods: ['POST'])]
    public function __invoke(int $id): JsonResponse
    {
        $workspace = $this->workspaceAccess->getWorkspaceOrFail($id);
        $this->workspaceAccess->assertOrganizer($workspace);
        $workspace->stopMeetingMode();
        $this->entityManager->flush();

        return $this->json(['ok' => true]);
    }
}
