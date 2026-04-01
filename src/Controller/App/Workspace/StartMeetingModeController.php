<?php

namespace App\Controller\App\Workspace;

use App\Workspace\WorkspaceAccess;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Routing\Attribute\Route;

final class StartMeetingModeController extends AbstractController
{
    public function __construct(
        private readonly WorkspaceAccess $workspaceAccess,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    #[Route('/app/workspaces/{id}/meeting/start', name: 'app_workspace_meeting_start', methods: ['POST'])]
    public function __invoke(int $id): RedirectResponse
    {
        $workspace = $this->workspaceAccess->getWorkspaceOrFail($id);
        $this->workspaceAccess->assertOrganizer($workspace);
        $workspace->startMeetingMode();
        $this->entityManager->flush();

        return $this->redirectToRoute('app_workspace_show', ['id' => $workspace->getId()]);
    }
}
