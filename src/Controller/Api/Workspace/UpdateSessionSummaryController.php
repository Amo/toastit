<?php

namespace App\Controller\Api\Workspace;

use App\Api\WorkspacePayloadBuilder;
use App\Meeting\ToastingSessionSummaryService;
use App\Workspace\WorkspaceAccessService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Routing\Attribute\Route;

final class UpdateSessionSummaryController extends AbstractController
{
    public function __construct(
        private readonly WorkspaceAccessService $workspaceAccess,
        private readonly ToastingSessionSummaryService $sessionSummary,
        private readonly WorkspacePayloadBuilder $workspacePayloadBuilder,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    #[Route('/api/workspaces/{id}/sessions/{sessionId}/summary', name: 'api_workspace_session_summary_update', methods: ['PUT'])]
    public function __invoke(Request $request, int $id, int $sessionId): JsonResponse
    {
        $workspace = $this->workspaceAccess->getWorkspaceOrFail($id);
        $this->workspaceAccess->assertOwner($workspace);

        if ($workspace->isSoloWorkspace()) {
            throw new AccessDeniedHttpException();
        }

        $payload = json_decode($request->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $summary = trim((string) ($payload['summary'] ?? ''));
        $session = $this->workspaceAccess->getToastingSessionOrFail($workspace, $sessionId);

        $this->sessionSummary->updateSessionSummary($session, $summary);
        $this->entityManager->flush();

        return $this->json([
            'ok' => true,
            'summary' => $this->workspacePayloadBuilder->buildSessionPayload($session),
        ]);
    }
}
