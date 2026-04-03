<?php

namespace App\Controller\Api\Workspace;

use App\Mailer\TransactionalMailer;
use App\Workspace\WorkspaceAccessService;
use App\Workspace\WorkspaceWorkflowService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class SendSessionSummaryController extends AbstractController
{
    public function __construct(
        private readonly WorkspaceAccessService $workspaceAccess,
        private readonly WorkspaceWorkflowService $workspaceWorkflow,
        private readonly TransactionalMailer $transactionalMailer,
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
    }

    #[Route('/api/workspaces/{id}/sessions/{sessionId}/summary/send', name: 'api_workspace_session_summary_send', methods: ['POST'])]
    public function __invoke(int $id, int $sessionId): JsonResponse
    {
        $workspace = $this->workspaceAccess->getWorkspaceOrFail($id);
        $this->workspaceAccess->assertOwner($workspace);

        if ($workspace->isSoloWorkspace()) {
            throw new AccessDeniedHttpException();
        }

        $session = $this->workspaceAccess->getToastingSessionOrFail($workspace, $sessionId);
        if (!$session->hasSummary()) {
            return $this->json([
                'ok' => false,
                'error' => 'missing_summary',
                'message' => 'No persisted summary exists for this session.',
            ], 400);
        }

        $recipients = array_values(array_filter(
            $this->workspaceWorkflow->getWorkspaceInvitees($workspace),
            static fn ($participant): bool => null !== $participant->getPublicEmail(),
        ));

        $toastUrlsById = [];
        foreach ($workspace->getItems() as $item) {
            if (null !== $item->getId()) {
                $toastUrlsById[$item->getId()] = $this->urlGenerator->generate('app_spa', [
                    'path' => sprintf('toasts/%d', $item->getId()),
                ], UrlGeneratorInterface::ABSOLUTE_URL);
            }
        }

        $this->transactionalMailer->sendToastingSessionSummary($workspace, $session, $recipients, $toastUrlsById);

        return $this->json([
            'ok' => true,
            'recipientCount' => count($recipients),
        ]);
    }
}
