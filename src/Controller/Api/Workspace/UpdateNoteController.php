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

final class UpdateNoteController extends AbstractController
{
    public function __construct(
        private readonly WorkspaceAccessService $workspaceAccess,
        private readonly WorkspaceNoteService $workspaceNoteService,
        private readonly WorkspacePayloadBuilder $workspacePayloadBuilder,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    #[Route('/api/workspaces/{id}/notes/{noteId}', name: 'api_workspace_note_update', methods: ['GET', 'PUT'])]
    public function __invoke(int $id, int $noteId, Request $request): JsonResponse
    {
        $workspace = $this->workspaceAccess->getWorkspaceOrFail($id);
        $currentUser = $this->workspaceAccess->getUserOrFail();
        $note = $this->workspaceAccess->getWorkspaceNoteOrFail($workspace, $noteId);

        if ($request->isMethod('GET')) {
            return $this->json([
                'ok' => true,
                'note' => $this->workspacePayloadBuilder->buildNotePayload($note, $currentUser),
            ]);
        }

        $payload = $request->toArray();
        $title = trim((string) ($payload['title'] ?? ''));
        $body = trim((string) ($payload['body'] ?? '')) ?: null;
        $isImportant = filter_var($payload['isImportant'] ?? false, FILTER_VALIDATE_BOOL);

        if ('' === $title) {
            return $this->json(['ok' => false, 'error' => 'missing_title'], 400);
        }

        $this->workspaceNoteService->updateNote($note, $currentUser, $title, $body, $isImportant);
        $this->entityManager->flush();

        return $this->json([
            'ok' => true,
            'note' => $this->workspacePayloadBuilder->buildNotePayload($note, $currentUser),
        ]);
    }
}
