<?php

namespace App\Controller\PublicApi\Toast;

use App\Workspace\WorkspaceAccessService;
use App\Workspace\WorkspaceWorkflowService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

final class UpdateToastAssigneeController extends AbstractController
{
    public function __construct(
        private readonly WorkspaceAccessService $workspaceAccess,
        private readonly WorkspaceWorkflowService $workspaceWorkflow,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    #[Route('/toasts/{id}/assignee', name: 'public_api_toast_assignee_update', methods: ['PATCH'])]
    public function __invoke(int $id, Request $request): JsonResponse
    {
        $toast = $this->workspaceAccess->getItemOrFail($id);
        $workspace = $toast->getWorkspace();
        $currentUser = $this->workspaceAccess->getUserOrFail();

        if (!$toast->isNew()) {
            return $this->json(['ok' => false, 'error' => 'toast_not_editable'], 400);
        }

        if (!$workspace->isOwnedBy($currentUser) && $toast->getAuthor()->getId() !== $currentUser->getId()) {
            return $this->json(['ok' => false, 'error' => 'forbidden'], 403);
        }

        $payload = $request->toArray();
        if (!array_key_exists('assigneeEmail', $payload)) {
            return $this->json(['ok' => false, 'error' => 'missing_assignee_email'], 400);
        }

        $assigneeEmail = trim((string) ($payload['assigneeEmail'] ?? ''));
        $owner = null;

        if ('' !== $assigneeEmail) {
            $owner = $this->workspaceWorkflow->findWorkspaceInviteeByEmail($workspace, $assigneeEmail);
            if (null === $owner) {
                return $this->json(['ok' => false, 'error' => 'unknown_assignee'], 400);
            }
        }

        $toast->setOwner($owner);
        $this->entityManager->flush();

        return $this->json([
            'ok' => true,
            'toast' => [
                'id' => $toast->getId(),
                'assigneeEmail' => $toast->getOwner()?->getPublicEmail(),
            ],
        ]);
    }
}
