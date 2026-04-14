<?php

namespace App\Controller\Api\Admin;

use App\Admin\RootDashboardService;
use App\Workspace\WorkspaceAccessService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

final class UserDeleteController extends AbstractController
{
    public function __construct(
        private readonly WorkspaceAccessService $workspaceAccess,
        private readonly RootDashboardService $rootDashboard,
    ) {
    }

    #[Route('/api/admin/users/{id<\d+>}', name: 'api_admin_user_delete', methods: ['DELETE'])]
    public function __invoke(int $id): JsonResponse
    {
        $this->workspaceAccess->assertRoot();

        $deleted = $this->rootDashboard->deletePrunableNeverConnectedUser($id);
        if (!$deleted) {
            return $this->json([
                'ok' => false,
                'message' => 'User cannot be deleted with current safety rules.',
            ], 400);
        }

        return $this->json(['ok' => true]);
    }
}
