<?php

namespace App\Controller\Api\Admin;

use App\Admin\RootDashboardService;
use App\Workspace\WorkspaceAccessService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

final class DashboardController extends AbstractController
{
    public function __construct(
        private readonly WorkspaceAccessService $workspaceAccess,
        private readonly RootDashboardService $rootDashboard,
    ) {
    }

    #[Route('/api/admin/dashboard', name: 'api_admin_dashboard', methods: ['GET'])]
    public function __invoke(): JsonResponse
    {
        $this->workspaceAccess->assertRoot();

        return $this->json([
            'overview' => $this->rootDashboard->buildOverview(),
            'users' => $this->rootDashboard->buildUsers(),
        ]);
    }
}
