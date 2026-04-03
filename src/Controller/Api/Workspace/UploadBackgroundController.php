<?php

namespace App\Controller\Api\Workspace;

use App\Workspace\WorkspaceAccessService;
use App\Workspace\WorkspaceBackgroundStorageService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

final class UploadBackgroundController extends AbstractController
{
    public function __construct(
        private readonly WorkspaceAccessService $workspaceAccess,
        private readonly WorkspaceBackgroundStorageService $backgroundStorage,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    #[Route('/api/workspaces/{id}/background', name: 'api_workspace_background_upload', methods: ['POST'])]
    public function __invoke(int $id, Request $request): JsonResponse
    {
        $workspace = $this->workspaceAccess->getWorkspaceOrFail($id);
        $this->workspaceAccess->assertOwner($workspace);

        if ($workspace->isInboxWorkspace()) {
            return $this->json(['ok' => false, 'error' => 'inbox_workspace_not_configurable'], 400);
        }

        $uploadedFile = $request->files->get('background');

        if (!$uploadedFile instanceof \Symfony\Component\HttpFoundation\File\UploadedFile) {
            return $this->json(['ok' => false, 'error' => 'missing_background'], 400);
        }

        $workspace->setPermalinkBackgroundUrl($this->backgroundStorage->store($workspace, $uploadedFile));
        $this->entityManager->flush();

        return $this->json(['ok' => true]);
    }
}
