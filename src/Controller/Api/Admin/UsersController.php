<?php

namespace App\Controller\Api\Admin;

use App\Admin\RootDashboardService;
use App\Api\AuthPayloadBuilder;
use App\Workspace\WorkspaceAccessService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

final class UsersController extends AbstractController
{
    public function __construct(
        private readonly WorkspaceAccessService $workspaceAccess,
        private readonly RootDashboardService $rootDashboard,
        private readonly AuthPayloadBuilder $authPayloadBuilder,
    ) {
    }

    #[Route('/api/admin/users', name: 'api_admin_users', methods: ['GET'])]
    public function __invoke(): JsonResponse
    {
        $this->workspaceAccess->assertRouteOrRoot();
        $currentUser = $this->workspaceAccess->getUserOrFail();

        return $this->json([
            'currentUser' => $this->authPayloadBuilder->buildUser($currentUser),
            'users' => $this->rootDashboard->buildUsers(),
            'prunableUsers' => $currentUser->isRoot() ? $this->rootDashboard->buildPrunableNeverConnectedUsers() : [],
        ]);
    }
}
