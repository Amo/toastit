<?php

namespace App\Controller\Api\Dashboard;

use App\Repository\WorkspaceRepository;
use App\Workspace\WorkspaceAccessService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

final class ReorderWorkspacesController extends AbstractController
{
    public function __construct(
        private readonly WorkspaceAccessService $workspaceAccess,
        private readonly WorkspaceRepository $workspaceRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    #[Route('/api/workspaces/reorder', name: 'api_workspaces_reorder', methods: ['POST'])]
    public function __invoke(Request $request): JsonResponse
    {
        $payload = $request->toArray();
        $workspaceIds = array_values(array_filter(
            array_map(static fn (mixed $workspaceId): int => is_numeric($workspaceId) ? (int) $workspaceId : 0, $payload['workspaceIds'] ?? []),
            static fn (int $workspaceId): bool => $workspaceId > 0
        ));

        if ([] === $workspaceIds) {
            return $this->json(['ok' => false, 'error' => 'missing_workspace_ids'], 400);
        }

        $currentUser = $this->workspaceAccess->getUserOrFail();
        $workspaces = $this->workspaceRepository->findForUser($currentUser);
        $membershipsByWorkspaceId = [];

        foreach ($workspaces as $workspace) {
            foreach ($workspace->getMemberships() as $membership) {
                if ($membership->getUser()->getId() === $currentUser->getId()) {
                    $membershipsByWorkspaceId[$workspace->getId()] = $membership;
                    break;
                }
            }
        }

        $orderedWorkspaceIds = [];

        foreach ($workspaceIds as $workspaceId) {
            if (isset($membershipsByWorkspaceId[$workspaceId]) && !in_array($workspaceId, $orderedWorkspaceIds, true)) {
                $orderedWorkspaceIds[] = $workspaceId;
            }
        }

        foreach (array_keys($membershipsByWorkspaceId) as $workspaceId) {
            if (!in_array($workspaceId, $orderedWorkspaceIds, true)) {
                $orderedWorkspaceIds[] = $workspaceId;
            }
        }

        foreach ($orderedWorkspaceIds as $index => $workspaceId) {
            $membershipsByWorkspaceId[$workspaceId]->setDisplayOrder($index + 1);
        }

        $this->entityManager->flush();

        return $this->json(['ok' => true]);
    }
}
