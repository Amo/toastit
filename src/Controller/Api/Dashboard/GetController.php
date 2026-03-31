<?php

namespace App\Controller\Api\Dashboard;

use App\Api\DashboardPayloadBuilder;
use App\Workspace\WorkspaceAccess;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

final class GetController extends AbstractController
{
    public function __construct(
        private readonly WorkspaceAccess $workspaceAccess,
        private readonly DashboardPayloadBuilder $dashboardPayloadBuilder,
    ) {
    }

    #[Route('/api/dashboard', name: 'api_dashboard_get', methods: ['GET'])]
    public function __invoke(): JsonResponse
    {
        return $this->json($this->dashboardPayloadBuilder->build($this->workspaceAccess->getUserOrFail()));
    }
}
