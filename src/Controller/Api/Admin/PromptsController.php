<?php

namespace App\Controller\Api\Admin;

use App\Admin\RootPromptService;
use App\Workspace\WorkspaceAccessService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

final class PromptsController extends AbstractController
{
    public function __construct(
        private readonly WorkspaceAccessService $workspaceAccess,
        private readonly RootPromptService $rootPrompt,
    ) {
    }

    #[Route('/api/admin/prompts', name: 'api_admin_prompts', methods: ['GET'])]
    public function __invoke(): JsonResponse
    {
        $this->workspaceAccess->assertRoot();

        return $this->json([
            'prompts' => $this->rootPrompt->listPrompts(),
        ]);
    }
}

