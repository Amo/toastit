<?php

namespace App\Controller\Api\Item;

use App\Entity\Toast;
use App\Workspace\WorkspaceAccess;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

final class ToastController extends AbstractController
{
    public function __construct(
        private readonly WorkspaceAccess $workspaceAccess,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    #[Route('/api/items/{id}/toast', name: 'api_item_toast', methods: ['POST'])]
    public function __invoke(int $id): JsonResponse
    {
        $item = $this->workspaceAccess->getItemOrFail($id);
        $workspace = $item->getWorkspace();
        $this->workspaceAccess->assertOwner($workspace);

        if (!$workspace->isSoloWorkspace() || !$item->isNew()) {
            return $this->json(['ok' => false, 'error' => 'toast_not_allowed'], 400);
        }

        $item
            ->setDiscussionStatus(Toast::DISCUSSION_TREATED)
            ->setIsBoosted(false)
            ->setStatusChangedAt(new \DateTimeImmutable());

        $this->entityManager->flush();

        return $this->json(['ok' => true, 'id' => $item->getId()]);
    }
}
