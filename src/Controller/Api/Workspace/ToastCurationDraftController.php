<?php

namespace App\Controller\Api\Workspace;

use App\Meeting\SessionSummaryUnavailableException;
use App\Workspace\ToastCurationDraftService;
use App\Workspace\WorkspaceAccessService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

final class ToastCurationDraftController extends AbstractController
{
    public function __construct(
        private readonly WorkspaceAccessService $workspaceAccess,
        private readonly ToastCurationDraftService $toastCurationDraft,
    ) {
    }

    #[Route('/api/workspaces/{id}/curation/draft', name: 'api_workspace_curation_draft', methods: ['POST'])]
    public function __invoke(int $id): JsonResponse
    {
        $workspace = $this->workspaceAccess->getWorkspaceOrFail($id);
        $this->workspaceAccess->assertOwner($workspace);

        try {
            $draft = $this->toastCurationDraft->generateDraft($workspace);
        } catch (SessionSummaryUnavailableException $exception) {
            $statusCode = match ($exception->getReason()) {
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
