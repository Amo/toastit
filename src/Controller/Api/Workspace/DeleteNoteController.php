<?php

namespace App\Controller\Api\Workspace;

use App\Workspace\WorkspaceAccessService;
use App\Workspace\WorkspaceNoteService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

final class DeleteNoteController extends AbstractController
{
    public function __construct(
        private readonly WorkspaceAccessService $workspaceAccess,
        private readonly WorkspaceNoteService $workspaceNoteService,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    #[Route('/api/workspaces/{id}/notes/{noteId}', name: 'api_workspace_note_delete', methods: ['DELETE'])]
    public function __invoke(int $id, int $noteId): JsonResponse
    {
        $workspace = $this->workspaceAccess->getWorkspaceOrFail($id);
        $currentUser = $this->workspaceAccess->getUserOrFail();
        $note = $this->workspaceAccess->getWorkspaceNoteOrFail($workspace, $noteId);

        $this->workspaceNoteService->deleteNote($note, $currentUser);
        $this->entityManager->flush();

        return $this->json(['ok' => true]);
    }
}
