<?php

namespace App\Controller\PublicApi\Workspace;

use App\Api\WorkspacePayloadBuilder;
use App\Entity\WorkspaceNote;
use App\Repository\WorkspaceNoteRepository;
use App\Workspace\WorkspaceAccessService;
use App\Workspace\WorkspaceNoteService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Routing\Attribute\Route;

final class ManageWorkspaceNoteController extends AbstractController
{
    public function __construct(
        private readonly WorkspaceAccessService $workspaceAccess,
        private readonly WorkspaceNoteRepository $workspaceNoteRepository,
        private readonly WorkspaceNoteService $workspaceNoteService,
        private readonly WorkspacePayloadBuilder $workspacePayloadBuilder,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    #[Route('/workspaces/{id}/notes', name: 'public_api_workspace_note_list', methods: ['GET'])]
    public function listNotes(int $id): JsonResponse
    {
        $workspace = $this->workspaceAccess->getWorkspaceOrFail($id);
        $currentUser = $this->workspaceAccess->getUserOrFail();
        $notes = $this->workspaceNoteRepository->findForWorkspace($workspace);

        return $this->json([
            'ok' => true,
            'notes' => array_map(
                fn (WorkspaceNote $note): array => $this->workspacePayloadBuilder->buildNotePayload($note, $currentUser),
                $notes
            ),
        ]);
    }

    #[Route('/workspaces/{id}/notes', name: 'public_api_workspace_note_create', methods: ['POST'])]
    public function createNote(int $id, Request $request): JsonResponse
    {
        $workspace = $this->workspaceAccess->getWorkspaceOrFail($id);
        $currentUser = $this->workspaceAccess->getUserOrFail();

        if ($workspace->isMeetingLive()) {
            throw new AccessDeniedHttpException();
        }

        $payload = $request->toArray();
        $title = trim((string) ($payload['title'] ?? ''));
        $body = trim((string) ($payload['body'] ?? '')) ?: null;
        $isImportant = filter_var($payload['isImportant'] ?? false, FILTER_VALIDATE_BOOL);

        if ('' === $title) {
            return $this->json(['ok' => false, 'error' => 'missing_title'], 400);
        }

        $note = $this->workspaceNoteService->createNote($workspace, $currentUser, $title, $body, $isImportant);
        $this->entityManager->flush();

        return $this->json([
            'ok' => true,
            'note' => $this->workspacePayloadBuilder->buildNotePayload($note, $currentUser),
        ], 201);
    }

    #[Route('/workspaces/{workspaceId}/notes/{noteId}', name: 'public_api_workspace_note_update', methods: ['PUT'])]
    public function updateNote(int $workspaceId, int $noteId, Request $request): JsonResponse
    {
        $workspace = $this->workspaceAccess->getWorkspaceOrFail($workspaceId);
        $currentUser = $this->workspaceAccess->getUserOrFail();
        $note = $this->workspaceAccess->getWorkspaceNoteOrFail($workspace, $noteId);

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
