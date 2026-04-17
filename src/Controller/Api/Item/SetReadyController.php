<?php

namespace App\Controller\Api\Item;

use App\Entity\Toast;
use App\Workspace\WorkspaceAccessService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

final class SetReadyController extends AbstractController
{
    public function __construct(
        private readonly WorkspaceAccessService $workspaceAccess,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    #[Route('/api/items/{id}/ready', name: 'api_item_ready_set', methods: ['POST'])]
    public function __invoke(int $id, Request $request): JsonResponse
    {
        $item = $this->workspaceAccess->getItemOrFail($id);
        $workspace = $item->getWorkspace();
        $currentUser = $this->workspaceAccess->getUserOrFail();

        if ($workspace->isSoloWorkspace()) {
            return $this->json(['ok' => false, 'error' => 'ready_not_allowed_for_solo_workspace'], 400);
        }

        if (!$item->isNew()) {
            return $this->json(['ok' => false, 'error' => 'toast_not_editable'], 400);
        }

        if (!$workspace->isOwnedBy($currentUser) && ($item->getOwner()?->getId()) !== $currentUser->getId()) {
            return $this->json(['ok' => false, 'error' => 'only_assignee_can_mark_ready'], 403);
        }

        $payload = $request->toArray();
        $ready = array_key_exists('ready', $payload)
            ? (bool) $payload['ready']
            : !$item->isReady();

        $item->setStatus($ready ? Toast::STATUS_READY : Toast::STATUS_PENDING);
        $this->entityManager->flush();

        return $this->json([
            'ok' => true,
            'id' => $item->getId(),
            'ready' => $item->isReady(),
            'status' => $item->getStatus(),
        ]);
    }
}
