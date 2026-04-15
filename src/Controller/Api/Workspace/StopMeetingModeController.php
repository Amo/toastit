<?php

namespace App\Controller\Api\Workspace;

use App\Api\WorkspacePayloadBuilder;
use App\Meeting\SessionSummaryUnavailableException;
use App\Meeting\ToastingSessionSummaryService;
use App\Workspace\WorkspaceAccessService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

final class StopMeetingModeController extends AbstractController
{
    public function __construct(
        private readonly WorkspaceAccessService $workspaceAccess,
        private readonly EntityManagerInterface $entityManager,
        private readonly ToastingSessionSummaryService $sessionSummary,
        private readonly WorkspacePayloadBuilder $workspacePayloadBuilder,
    ) {
    }

    #[Route('/api/workspaces/{id}/meeting/stop', name: 'api_workspace_meeting_stop', methods: ['POST'])]
    public function __invoke(int $id): JsonResponse
    {
        $workspace = $this->workspaceAccess->getWorkspaceOrFail($id);
        $currentUser = $this->workspaceAccess->getUserOrFail();
        $this->workspaceAccess->assertOwner($workspace);

        if ($workspace->isSoloWorkspace()) {
            throw new AccessDeniedHttpException();
        }

        $activeSession = $workspace->getActiveToastingSession();
        $workspace->stopMeetingMode($currentUser);
        $this->entityManager->flush();

        $summaryPayload = null;
        $summaryError = null;

        if (null !== $activeSession) {
            try {
                $session = $this->sessionSummary->generateSessionSummary($workspace, $activeSession, $currentUser);
                $this->entityManager->flush();
                $summaryPayload = $this->workspacePayloadBuilder->buildSessionPayload($session, $currentUser);
            } catch (SessionSummaryUnavailableException $exception) {
                $summaryError = [
                    'error' => $exception->getReason(),
                    'message' => $exception->getMessage(),
                ];
            }
        }

        return $this->json([
            'ok' => true,
            'sessionId' => $activeSession?->getId(),
            'summary' => $summaryPayload,
            'summaryError' => $summaryError,
        ]);
    }
}
