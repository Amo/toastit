<?php

namespace App\Controller\Api\Admin;

use App\Admin\RootPromptService;
use App\Workspace\WorkspaceAccessService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

final class PromptRollbackController extends AbstractController
{
    public function __construct(
        private readonly WorkspaceAccessService $workspaceAccess,
        private readonly RootPromptService $rootPrompt,
    ) {
    }

    #[Route('/api/admin/prompts/{code}/rollback/{versionNumber}', name: 'api_admin_prompt_rollback', methods: ['POST'])]
    public function __invoke(string $code, int $versionNumber): JsonResponse
    {
        $this->workspaceAccess->assertRoot();
        $actor = $this->workspaceAccess->getUserOrFail();

        $updatedPrompt = $this->rootPrompt->rollbackPromptVersion($code, $versionNumber, $actor);
        if (null === $updatedPrompt) {
            return $this->json(['ok' => false, 'error' => 'prompt_or_version_not_found'], 404);
        }

        return $this->json(['ok' => true, 'prompt' => $updatedPrompt]);
    }
}

