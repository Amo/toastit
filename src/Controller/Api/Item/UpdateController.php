<?php

namespace App\Controller\Api\Item;

use App\Workspace\WorkspaceAccessService;
use App\Workspace\WorkspaceWorkflowService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

final class UpdateController extends AbstractController
{
    public function __construct(
        private readonly WorkspaceAccessService $workspaceAccess,
        private readonly WorkspaceWorkflowService $workspaceWorkflow,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    #[Route('/api/items/{id}', name: 'api_item_update', methods: ['PUT'])]
    public function __invoke(int $id, Request $request): JsonResponse
    {
        $item = $this->workspaceAccess->getItemOrFail($id);
        $workspace = $item->getWorkspace();
        $currentUser = $this->workspaceAccess->getUserOrFail();

        if (!$item->isNew()) {
            return $this->json(['ok' => false, 'error' => 'toast_not_editable'], 400);
        }

        if (!$workspace->isOwnedBy($currentUser) && $item->getAuthor()->getId() !== $currentUser->getId()) {
            return $this->json(['ok' => false, 'error' => 'forbidden'], 403);
        }

        $payload = $request->toArray();
        $title = trim((string) ($payload['title'] ?? ''));

        if ('' === $title) {
            return $this->json(['ok' => false, 'error' => 'missing_title'], 400);
        }

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
            ->setTitle($title)
            ->setDescription(trim((string) ($payload['description'] ?? '')) ?: null)
            ->setOwner($owner)
            ->setDueAt($dueAt);

        $this->entityManager->flush();

        return $this->json(['ok' => true, 'itemId' => $item->getId()]);
    }
}
