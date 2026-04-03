<?php

namespace App\Controller\Api\Workspace;

use App\Workspace\ToastCurationExecutionService;
use App\Workspace\WorkspaceAccessService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

final class ToastCurationApplyController extends AbstractController
{
    public function __construct(
        private readonly WorkspaceAccessService $workspaceAccess,
        private readonly ToastCurationExecutionService $toastCurationExecution,
    ) {
    }

    #[Route('/api/workspaces/{id}/curation/apply', name: 'api_workspace_curation_apply', methods: ['POST'])]
    public function __invoke(int $id, Request $request): JsonResponse
    {
        $workspace = $this->workspaceAccess->getWorkspaceOrFail($id);
        $actor = $this->workspaceAccess->getUserOrFail();
        $this->workspaceAccess->assertOwner($workspace);
        $this->workspaceAccess->assertMeetingModeIdle($workspace);

        $payload = $request->toArray();
        $actions = $payload['actions'] ?? null;

        if (!is_array($actions)) {
            return $this->json(['ok' => false, 'error' => 'invalid_actions'], 400);
        }

        $result = $this->toastCurationExecution->applyDraft($workspace, $actor, $actions);

        return $this->json([
            'ok' => true,
            'result' => $result,
        ]);
    }
}
