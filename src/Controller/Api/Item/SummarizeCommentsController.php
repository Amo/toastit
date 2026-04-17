<?php

namespace App\Controller\Api\Item;

use App\Meeting\SessionSummaryUnavailableException;
use App\Workspace\ToastCommentSummaryService;
use App\Workspace\WorkspaceAccessService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

final class SummarizeCommentsController extends AbstractController
{
    public function __construct(
        private readonly WorkspaceAccessService $workspaceAccess,
        private readonly ToastCommentSummaryService $toastCommentSummary,
    ) {
    }

    #[Route('/api/items/{id}/comments/summary', name: 'api_item_comment_summary', methods: ['POST'])]
    public function __invoke(int $id): JsonResponse
    {
        $item = $this->workspaceAccess->getItemOrFail($id);
        $currentUser = $this->workspaceAccess->getUserOrFail();

        try {
            $summary = $this->toastCommentSummary->summarize($item, $currentUser);
        } catch (SessionSummaryUnavailableException $exception) {
            $statusCode = match ($exception->getReason()) {
                'missing_comments' => 400,
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
            'summary' => $summary,
        ]);
    }
}
