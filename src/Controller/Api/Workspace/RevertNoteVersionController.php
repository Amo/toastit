<?php

namespace App\Controller\Api\Workspace;

use App\Api\WorkspacePayloadBuilder;
use App\Workspace\WorkspaceAccessService;
use App\Workspace\WorkspaceNoteService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

final class RevertNoteVersionController extends AbstractController
{
    public function __construct(
        private readonly WorkspaceAccessService $workspaceAccess,
        private readonly WorkspaceNoteService $workspaceNoteService,
        private readonly WorkspacePayloadBuilder $workspacePayloadBuilder,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    #[Route('/api/workspaces/{id}/notes/{noteId}/versions/{versionId}/revert', name: 'api_workspace_note_version_revert', methods: ['POST'])]
    public function __invoke(int $id, int $noteId, int $versionId): JsonResponse
    {
        $workspace = $this->workspaceAccess->getWorkspaceOrFail($id);
        $currentUser = $this->workspaceAccess->getUserOrFail();
        $note = $this->workspaceAccess->getWorkspaceNoteOrFail($workspace, $noteId);
        $version = $this->workspaceAccess->getWorkspaceNoteVersionOrFail($note, $versionId);

        $this->workspaceNoteService->revertToVersion($note, $version, $currentUser);
        $this->entityManager->flush();

        return $this->json([
            'ok' => true,
            'note' => $this->workspacePayloadBuilder->buildNotePayload($note, $currentUser),
        ]);
    }
}
