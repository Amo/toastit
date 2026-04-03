<?php

namespace App\Controller\Api\Item;

use App\Workspace\ToastCurationExecutionService;
use App\Workspace\WorkspaceAccessService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

final class ExecutionPlanApplyController extends AbstractController
{
    public function __construct(
        private readonly WorkspaceAccessService $workspaceAccess,
        private readonly ToastCurationExecutionService $toastCurationExecution,
    ) {
    }

    #[Route('/api/items/{id}/execution-plan/apply', name: 'api_item_execution_plan_apply', methods: ['POST'])]
    public function __invoke(int $id, Request $request): JsonResponse
    {
        $item = $this->workspaceAccess->getItemOrFail($id);
        $workspace = $item->getWorkspace();
        $actor = $this->workspaceAccess->getUserOrFail();
        $this->workspaceAccess->assertOwner($workspace);
        $this->workspaceAccess->assertMeetingModeActive($workspace);

        $payload = $request->toArray();
        $action = $payload['action'] ?? null;

        if (!is_array($action) || (($action['type'] ?? null) !== 'create_follow_up')) {
            return $this->json(['ok' => false, 'error' => 'invalid_action'], 400);
        }

        $action['toastId'] = $item->getId();
        $result = $this->toastCurationExecution->applyDraft($workspace, $actor, [$action]);

        return $this->json([
            'ok' => true,
            'result' => $result,
        ]);
    }
}
