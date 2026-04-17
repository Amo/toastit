<?php

namespace App\Controller\Api\Workspace;

use App\Entity\Toast;
use App\Workspace\ToastCreationService;
use App\Workspace\WorkspaceAccessService;
use App\Workspace\WorkspaceWorkflowService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

final class CreateItemController extends AbstractController
{
    public function __construct(
        private readonly WorkspaceAccessService $workspaceAccess,
        private readonly WorkspaceWorkflowService $workspaceWorkflow,
        private readonly ToastCreationService $toastCreation,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    #[Route('/api/workspaces/{id}/items', name: 'api_workspace_item_create', methods: ['POST'])]
    public function __invoke(int $id, Request $request): JsonResponse
    {
        $workspace = $this->workspaceAccess->getWorkspaceOrFail($id);
        $payload = $request->toArray();
        $title = trim((string) ($payload['title'] ?? ''));
        $description = trim((string) ($payload['description'] ?? '')) ?: null;

        if ('' === $title && null !== $description) {
            $title = $this->buildFallbackTitle($description);
        }

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

        $item = $this->toastCreation->createToast(
            $workspace,
            $this->workspaceAccess->getUserOrFail(),
            $title,
            $description,
            $owner,
            $dueAt,
            null,
            true,
        );

        $this->entityManager->flush();

        return $this->json([
            'ok' => true,
            'itemId' => $item->getId(),
            'aiRefinementPending' => $item->isAiRefinementPending(),
        ]);
    }

    private function buildFallbackTitle(string $description): string
    {
        $normalized = preg_replace('/\s+/', ' ', trim($description)) ?? trim($description);

        if ('' === $normalized) {
            return '';
        }

        if (mb_strlen($normalized) <= 72) {
            return $normalized;
        }

        return mb_substr($normalized, 0, 69).'...';
    }
}
