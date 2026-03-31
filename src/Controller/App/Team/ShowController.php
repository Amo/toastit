<?php

namespace App\Controller\App\Team;

use App\Api\TeamPayloadBuilder;
use App\Security\JwtTokenManager;
use App\Workspace\WorkspaceAccess;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class ShowController extends AbstractController
{
    public function __construct(
        private readonly WorkspaceAccess $workspaceAccess,
        private readonly TeamPayloadBuilder $teamPayloadBuilder,
        private readonly JwtTokenManager $jwtTokenManager,
    ) {
    }

    #[Route('/app/teams/{id}', name: 'app_team_show', methods: ['POST'])]
    public function __invoke(int $id): Response
    {
        throw new \LogicException('GET handled by SPA shell.');
    }
}
