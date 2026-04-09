<?php

namespace App\Controller\Api\Admin;

use App\Admin\RootPromptService;
use App\Workspace\WorkspaceAccessService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

final class PromptDetailController extends AbstractController
{
    public function __construct(
        private readonly WorkspaceAccessService $workspaceAccess,
        private readonly RootPromptService $rootPrompt,
    ) {
    }

    #[Route('/api/admin/prompts/{code}', name: 'api_admin_prompt_detail', methods: ['GET'])]
    public function __invoke(string $code): JsonResponse
    {
        $this->workspaceAccess->assertRoot();

        $prompt = $this->rootPrompt->getPrompt($code);
        if (null === $prompt) {
            return $this->json(['ok' => false, 'error' => 'prompt_not_found'], 404);
        }

        return $this->json(['ok' => true, 'prompt' => $prompt]);
    }
}

