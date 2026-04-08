<?php

namespace App\Controller\Api\Item;

use App\Meeting\SessionSummaryUnavailableException;
use App\Workspace\ToastExecutionPlanDraftService;
use App\Workspace\WorkspaceAccessService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

final class ExecutionPlanDraftController extends AbstractController
{
    public function __construct(
        private readonly WorkspaceAccessService $workspaceAccess,
        private readonly ToastExecutionPlanDraftService $executionPlanDraft,
    ) {
    }

    #[Route('/api/items/{id}/execution-plan/draft', name: 'api_item_execution_plan_draft', methods: ['POST'])]
    public function __invoke(int $id): JsonResponse
    {
        $item = $this->workspaceAccess->getItemOrFail($id);
        $workspace = $item->getWorkspace();
        $this->workspaceAccess->assertOwner($workspace);
        $this->workspaceAccess->assertMeetingModeActive($workspace);

        try {
            $draft = $this->executionPlanDraft->generate($item, $this->workspaceAccess->getUserOrFail());
        } catch (SessionSummaryUnavailableException $exception) {
            $statusCode = match ($exception->getReason()) {
                'missing_decision_notes' => 400,
                'xai_not_configured' => 503,
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
            'draft' => $draft,
        ]);
    }
}
