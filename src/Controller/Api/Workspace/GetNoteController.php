<?php

namespace App\Controller\Api\Workspace;

use App\Api\WorkspacePayloadBuilder;
use App\Workspace\WorkspaceAccessService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

final class GetNoteController extends AbstractController
{
    public function __construct(
        private readonly WorkspaceAccessService $workspaceAccess,
        private readonly WorkspacePayloadBuilder $workspacePayloadBuilder,
    ) {
    }

    #[Route('/api/workspaces/{id}/notes/{noteId}', name: 'api_workspace_note_get', methods: ['GET'])]
    public function __invoke(int $id, int $noteId): JsonResponse
    {
        $workspace = $this->workspaceAccess->getWorkspaceOrFail($id);
        $currentUser = $this->workspaceAccess->getUserOrFail();
        $note = $this->workspaceAccess->getWorkspaceNoteOrFail($workspace, $noteId);

        return $this->json([
            'ok' => true,
            'note' => $this->workspacePayloadBuilder->buildNotePayload($note, $currentUser),
        ]);
    }
}
