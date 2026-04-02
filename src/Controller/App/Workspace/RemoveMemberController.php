<?php

namespace App\Controller\App\Workspace;

use App\Entity\WorkspaceMember;
use App\Workspace\WorkspaceAccessService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Routing\Attribute\Route;

final class RemoveMemberController extends AbstractController
{
    public function __construct(
        private readonly WorkspaceAccessService $workspaceAccess,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    #[Route('/app/workspaces/{workspaceId}/members/{memberId}/remove', name: 'app_workspace_member_remove', methods: ['POST'])]
    public function __invoke(int $workspaceId, int $memberId): RedirectResponse
    {
        $workspace = $this->workspaceAccess->getWorkspaceOrFail($workspaceId);
        $this->workspaceAccess->assertOwner($workspace);
        $membership = $this->entityManager->getRepository(WorkspaceMember::class)->find($memberId);

        if (!$membership instanceof WorkspaceMember || $membership->getWorkspace()->getId() !== $workspace->getId()) {
            throw $this->createNotFoundException();
        }

        if ($membership->isOwner() && $workspace->getOwnerCount() <= 1) {
            throw $this->createAccessDeniedException();
        }

        $this->entityManager->remove($membership);
        $this->entityManager->flush();

        return $this->redirectToRoute('app_workspace_show', ['id' => $workspace->getId()]);
    }
}
