<?php

namespace App\Controller\Api\Item;

use App\Workspace\WorkspaceAccessService;
use App\Workspace\WorkspaceWorkflowService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

final class SaveDecisionNotesController extends AbstractController
{
    public function __construct(
        private readonly WorkspaceAccessService $workspaceAccess,
        private readonly WorkspaceWorkflowService $workspaceWorkflow,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    #[Route('/api/items/{id}/decision-notes', name: 'api_item_decision_notes_save', methods: ['POST'])]
    public function __invoke(int $id, Request $request): JsonResponse
    {
        $item = $this->workspaceAccess->getItemOrFail($id);
        $workspace = $item->getWorkspace();
        $this->workspaceAccess->assertOwner($workspace);
        $this->workspaceAccess->assertMeetingModeActive($workspace);

        $payload = $request->toArray();
        $ownerId = is_numeric($payload['ownerId'] ?? null) ? (int) $payload['ownerId'] : 0;
        $owner = $this->workspaceWorkflow->findWorkspaceInviteeById($workspace, $ownerId);
        $dueAt = null;

        if (!empty($payload['dueOn'])) {
            try {
                $dueAt = new \DateTimeImmutable((string) $payload['dueOn']);
            } catch (\Exception) {
                return $this->json(['ok' => false, 'error' => 'invalid_due_on'], 400);
            }
        }

        $item
            ->setDiscussionNotes(trim((string) ($payload['discussionNotes'] ?? '')) ?: null)
            ->setOwner($owner)
            ->setDueAt($dueAt);

        $this->entityManager->flush();

        return $this->json(['ok' => true]);
    }
}
