<?php

namespace App\Controller\Api\Inbox;

use App\Api\WorkspacePayloadBuilder;
use App\Workspace\InboxWorkspaceService;
use App\Workspace\WorkspaceAccessService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

final class GetWorkspaceController extends AbstractController
{
    public function __construct(
        private readonly WorkspaceAccessService $workspaceAccess,
        private readonly InboxWorkspaceService $inboxWorkspace,
        private readonly WorkspacePayloadBuilder $workspacePayloadBuilder,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    #[Route('/api/inbox/workspace', name: 'api_inbox_workspace_get', methods: ['GET'])]
    public function __invoke(): JsonResponse
    {
        $currentUser = $this->workspaceAccess->getUserOrFail();
        $workspace = $this->inboxWorkspace->getOrCreateInboxWorkspace($currentUser);

        $this->entityManager->flush();

        return $this->json($this->workspacePayloadBuilder->build($workspace, $currentUser));
    }
}
