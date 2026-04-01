<?php

namespace App\Controller\App\Item;

use App\Entity\Toast;
use App\Workspace\WorkspaceAccess;
use App\Workspace\WorkspaceWorkflow;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

final class ToggleBoostController extends AbstractController
{
    public function __construct(
        private readonly WorkspaceAccess $workspaceAccess,
        private readonly WorkspaceWorkflow $workspaceWorkflow,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    #[Route('/app/items/{id}/boost', name: 'app_item_boost_toggle', methods: ['POST'])]
    public function __invoke(int $id, Request $request): Response
    {
        $item = $this->workspaceAccess->getItemOrFail($id);
        $workspace = $item->getWorkspace();
        $this->workspaceAccess->assertOrganizer($workspace);
        $this->workspaceAccess->assertMeetingModeActive($workspace);

        if ($item->isBoosted()) {
            $item->setIsBoosted(false);
        } else {
            $item
                ->setStatus(Toast::STATUS_OPEN)
                ->setIsBoosted(true)
                ->setBoostRank($this->workspaceWorkflow->nextBoostRank($workspace));
        }

        $this->entityManager->flush();

        if ($request->isXmlHttpRequest() || str_contains((string) $request->headers->get('Accept'), 'application/json')) {
            return new JsonResponse([
                'id' => $item->getId(),
                'boosted' => $item->isBoosted(),
                'boostRank' => $item->getBoostRank(),
            ]);
        }

        return $this->redirectToRoute('app_workspace_show', ['id' => $workspace->getId()]);
    }
}
