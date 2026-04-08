<?php

namespace App\Controller\Api\Action;

use App\Api\MyActionsPayloadBuilder;
use App\Workspace\WorkspaceAccessService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

final class GetController extends AbstractController
{
    public function __construct(
        private readonly WorkspaceAccessService $workspaceAccess,
        private readonly MyActionsPayloadBuilder $myActionsPayloadBuilder,
    ) {
    }

    #[Route('/api/actions', name: 'api_actions_get', methods: ['GET'])]
    public function __invoke(): JsonResponse
    {
        return $this->json($this->myActionsPayloadBuilder->build($this->workspaceAccess->getUserOrFail()));
    }
}
