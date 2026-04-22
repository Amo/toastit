<?php

namespace App\Controller\Api\Workspace;

use App\Meeting\SessionSummaryUnavailableException;
use App\Workspace\ToastDraftRefinementService;
use App\Workspace\WorkspaceAccessService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

final class RefineToastDraftController extends AbstractController
{
    public function __construct(
        private readonly WorkspaceAccessService $workspaceAccess,
        private readonly ToastDraftRefinementService $toastDraftRefinement,
    ) {
    }

    #[Route('/api/workspaces/{id}/items/draft/refine', name: 'api_workspace_item_draft_refine', methods: ['POST'])]
    public function __invoke(int $id, Request $request): JsonResponse
    {
        $workspace = $this->workspaceAccess->getWorkspaceOrFail($id);
        $payload = $request->toArray();

        try {
            $draft = $this->toastDraftRefinement->refine(
                $workspace,
                (string) ($payload['title'] ?? ''),
                (string) ($payload['description'] ?? ''),
                $this->workspaceAccess->getUserOrFail(),
                (string) ($payload['dueOn'] ?? ''),
            );
        } catch (SessionSummaryUnavailableException $exception) {
            $statusCode = match ($exception->getReason()) {
                'missing_input' => 400,
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
