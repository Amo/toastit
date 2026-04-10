<?php

namespace App\Controller\Api\Item;

use App\Entity\Toast;
use App\Workspace\WorkspaceAccessService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Routing\Attribute\Route;

final class ToggleVetoController extends AbstractController
{
    public function __construct(
        private readonly WorkspaceAccessService $workspaceAccess,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    #[Route('/api/items/{id}/veto', name: 'api_item_veto_toggle', methods: ['POST'])]
    public function __invoke(int $id): JsonResponse
    {
        $item = $this->workspaceAccess->getItemOrFail($id);
        $workspace = $item->getWorkspace();
        $this->workspaceAccess->assertOwner($workspace);
        $this->workspaceAccess->assertMeetingModeIdle($workspace);

        if ($item->isToasted()) {
            throw new AccessDeniedHttpException();
        }

        if ($item->isVetoed()) {
            $item
                ->setStatus(Toast::STATUS_PENDING)
                ->setStatusChangedAt(null);
        } else {
            $item
                ->setStatus(Toast::STATUS_DISCARDED)
                ->setIsBoosted(false)
                ->setStatusChangedAt(new \DateTimeImmutable());
        }

        $this->entityManager->flush();

        return $this->json([
            'ok' => true,
            'id' => $item->getId(),
            'discarded' => $item->isVetoed(),
        ]);
    }
}
