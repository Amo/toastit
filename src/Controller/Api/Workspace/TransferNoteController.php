<?php

namespace App\Controller\Api\Workspace;

use App\Api\WorkspacePayloadBuilder;
use App\Workspace\WorkspaceAccessService;
use App\Workspace\WorkspaceNoteService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

final class TransferNoteController extends AbstractController
{
    public function __construct(
        private readonly WorkspaceAccessService $workspaceAccess,
        private readonly WorkspaceNoteService $workspaceNoteService,
        private readonly WorkspacePayloadBuilder $workspacePayloadBuilder,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    #[Route('/api/workspaces/{id}/notes/{noteId}/transfer', name: 'api_workspace_note_transfer', methods: ['POST'])]
    public function __invoke(int $id, int $noteId, Request $request): JsonResponse
    {
        $sourceWorkspace = $this->workspaceAccess->getWorkspaceOrFail($id);
        $currentUser = $this->workspaceAccess->getUserOrFail();
        $this->workspaceAccess->assertOwner($sourceWorkspace);
        $note = $this->workspaceAccess->getWorkspaceNoteOrFail($sourceWorkspace, $noteId);

        $payload = $request->toArray();
        $targetWorkspaceId = is_numeric($payload['targetWorkspaceId'] ?? null) ? (int) $payload['targetWorkspaceId'] : 0;

        if ($targetWorkspaceId <= 0 || $targetWorkspaceId === $sourceWorkspace->getId()) {
            return $this->json(['ok' => false, 'error' => 'invalid_target_workspace'], 400);
        }

        $targetWorkspace = $this->workspaceAccess->getWorkspaceOrFail($targetWorkspaceId);
        $note = $this->workspaceNoteService->transferNote($note, $targetWorkspace, $currentUser);
        $this->entityManager->flush();

        return $this->json([
            'ok' => true,
            'workspaceId' => $targetWorkspace->getId(),
            'note' => $this->workspacePayloadBuilder->buildNotePayload($note, $currentUser),
        ]);
    }
}
