<?php

namespace App\Controller\Api\Item;

use App\Entity\Toast;
use App\Workspace\WorkspaceAccessService;
use App\Workspace\WorkspaceWorkflowService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Routing\Attribute\Route;

final class ToggleBoostController extends AbstractController
{
    public function __construct(
        private readonly WorkspaceAccessService $workspaceAccess,
        private readonly WorkspaceWorkflowService $workspaceWorkflow,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    #[Route('/api/items/{id}/boost', name: 'api_item_boost_toggle', methods: ['POST'])]
    public function __invoke(int $id): JsonResponse
    {
        $item = $this->workspaceAccess->getItemOrFail($id);
        $workspace = $item->getWorkspace();
        $this->workspaceAccess->assertOwner($workspace);
        $this->workspaceAccess->assertMeetingModeIdle($workspace);

        if ($item->isToasted()) {
            throw new AccessDeniedHttpException();
        }

        if ($item->isBoosted()) {
            $item->setIsBoosted(false);
        } else {
            if ($item->isVetoed()) {
                $item->setStatus(Toast::STATUS_PENDING);
            }

            $item
                ->setIsBoosted(true)
                ->setBoostRank($this->workspaceWorkflow->nextBoostRank($workspace));
        }

        $this->entityManager->flush();

        return $this->json([
            'ok' => true,
            'id' => $item->getId(),
            'boosted' => $item->isBoosted(),
            'boostRank' => $item->getBoostRank(),
        ]);
    }
}
