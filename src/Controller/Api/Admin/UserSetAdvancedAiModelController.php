<?php

namespace App\Controller\Api\Admin;

use App\Admin\RootDashboardService;
use App\Workspace\WorkspaceAccessService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

final class UserSetAdvancedAiModelController extends AbstractController
{
    public function __construct(
        private readonly WorkspaceAccessService $workspaceAccess,
        private readonly RootDashboardService $rootDashboard,
    ) {
    }

    #[Route('/api/admin/users/{id<\d+>}/advanced-ai-model', name: 'api_admin_user_advanced_ai_model', methods: ['PUT'])]
    public function __invoke(int $id, Request $request): JsonResponse
    {
        $this->workspaceAccess->assertRoot();
        $payload = $request->toArray();
        $enabled = (bool) ($payload['enabled'] ?? false);

        $user = $this->rootDashboard->setAdvancedAiModelEnabled($id, $enabled);
        if (!is_array($user)) {
            return $this->json([
                'ok' => false,
                'error' => 'user_not_found',
            ], 404);
        }

        return $this->json([
            'ok' => true,
            'user' => $user,
        ]);
    }
}
