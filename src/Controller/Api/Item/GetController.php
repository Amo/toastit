<?php

namespace App\Controller\Api\Item;

use App\Api\WorkspacePayloadBuilder;
use App\Workspace\WorkspaceAccessService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

final class GetController extends AbstractController
{
    public function __construct(
        private readonly WorkspaceAccessService $workspaceAccess,
        private readonly WorkspacePayloadBuilder $workspacePayloadBuilder,
    ) {
    }

    #[Route('/api/toasts/{id}', name: 'api_toast_get', methods: ['GET'])]
    public function __invoke(int $id): JsonResponse
    {
        $item = $this->workspaceAccess->getItemOrFail($id);
        $currentUser = $this->workspaceAccess->getUserOrFail();
        $payload = $this->workspacePayloadBuilder->build($item->getWorkspace(), $currentUser);
        $payload['selectedToastId'] = $item->getId();

        return $this->json($payload);
    }
}
