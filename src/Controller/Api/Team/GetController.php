<?php

namespace App\Controller\Api\Team;

use App\Api\TeamPayloadBuilder;
use App\Workspace\WorkspaceAccess;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

final class GetController extends AbstractController
{
    public function __construct(
        private readonly WorkspaceAccess $workspaceAccess,
        private readonly TeamPayloadBuilder $teamPayloadBuilder,
    ) {
    }

    #[Route('/api/teams/{id}', name: 'api_team_get', methods: ['GET'])]
    public function __invoke(int $id): JsonResponse
    {
        return $this->json($this->teamPayloadBuilder->build($this->workspaceAccess->getTeamOrFail($id)));
    }
}
