<?php

namespace App\Controller\App\Workspace;

use App\Workspace\WorkspaceAccess;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Routing\Attribute\Route;

final class StopMeetingModeController extends AbstractController
{
    public function __construct(
        private readonly WorkspaceAccess $workspaceAccess,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    #[Route('/app/workspaces/{id}/meeting/stop', name: 'app_workspace_meeting_stop', methods: ['POST'])]
    public function __invoke(int $id): RedirectResponse
    {
        $workspace = $this->workspaceAccess->getWorkspaceOrFail($id);
        $this->workspaceAccess->assertOwner($workspace);
        $workspace->stopMeetingMode();
        $this->entityManager->flush();

        return $this->redirectToRoute('app_workspace_show', ['id' => $workspace->getId()]);
    }
}
