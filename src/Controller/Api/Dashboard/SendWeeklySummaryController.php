<?php

namespace App\Controller\Api\Dashboard;

use App\Meeting\SessionSummaryUnavailableException;
use App\Workspace\TodoDigestService;
use App\Workspace\WorkspaceAccessService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

final class SendWeeklySummaryController extends AbstractController
{
    public function __construct(
        private readonly WorkspaceAccessService $workspaceAccess,
        private readonly TodoDigestService $todoDigestService,
    ) {
    }

    #[Route('/api/dashboard/weekly-summary', name: 'api_dashboard_weekly_summary_send', methods: ['POST'])]
    public function __invoke(): JsonResponse
    {
        $user = $this->workspaceAccess->getUserOrFail();

        try {
            $this->todoDigestService->sendWeeklySummary($user);
        } catch (SessionSummaryUnavailableException $exception) {
            return $this->json([
                'ok' => false,
                'error' => $exception->getReason(),
                'message' => $exception->getMessage(),
            ], 'xai_not_configured' === $exception->getReason() ? 503 : 502);
        }

        return $this->json([
            'ok' => true,
        ]);
    }
}
