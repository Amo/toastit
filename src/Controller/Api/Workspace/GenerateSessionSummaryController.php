<?php

namespace App\Controller\Api\Workspace;

use App\Api\WorkspacePayloadBuilder;
use App\Meeting\SessionSummaryUnavailableException;
use App\Meeting\ToastingSessionSummaryService;
use App\Workspace\WorkspaceAccessService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Routing\Attribute\Route;

final class GenerateSessionSummaryController extends AbstractController
{
    public function __construct(
        private readonly WorkspaceAccessService $workspaceAccess,
        private readonly ToastingSessionSummaryService $sessionSummary,
        private readonly WorkspacePayloadBuilder $workspacePayloadBuilder,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    #[Route('/api/workspaces/{id}/sessions/{sessionId}/summary/generate', name: 'api_workspace_session_summary_generate', methods: ['POST'])]
    public function __invoke(int $id, int $sessionId): JsonResponse
    {
        $workspace = $this->workspaceAccess->getWorkspaceOrFail($id);
        $currentUser = $this->workspaceAccess->getUserOrFail();
        $this->workspaceAccess->assertOwner($workspace);

        if ($workspace->isSoloWorkspace()) {
            throw new AccessDeniedHttpException();
        }

        $session = $this->workspaceAccess->getToastingSessionOrFail($workspace, $sessionId);

        try {
            $session = $this->sessionSummary->generateSessionSummary($workspace, $session, $currentUser);
            $this->entityManager->flush();
        } catch (SessionSummaryUnavailableException $exception) {
            $statusCode = match ($exception->getReason()) {
                'xai_not_configured' => 503,
                'xai_request_timeout' => 504,
                default => 502,
            };

            return $this->json([
                'ok' => false,
                'error' => $exception->getReason(),
                'message' => $exception->getMessage(),
            ], $statusCode);
        }

        return $this->json([
            'ok' => true,
            'summary' => $this->workspacePayloadBuilder->buildSessionPayload($session, $currentUser),
        ]);
    }
}
