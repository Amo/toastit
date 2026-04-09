<?php

namespace App\Controller\Api\Admin;

use App\Admin\RootPromptService;
use App\Workspace\WorkspaceAccessService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

final class PromptUpdateController extends AbstractController
{
    public function __construct(
        private readonly WorkspaceAccessService $workspaceAccess,
        private readonly RootPromptService $rootPrompt,
    ) {
    }

    #[Route('/api/admin/prompts/{code}', name: 'api_admin_prompt_update', methods: ['PUT'])]
    public function __invoke(string $code, Request $request): JsonResponse
    {
        $this->workspaceAccess->assertRoot();
        $actor = $this->workspaceAccess->getUserOrFail();

        $payload = $request->toArray();
        $systemPrompt = trim((string) ($payload['systemPrompt'] ?? ''));
        $userPromptTemplate = trim((string) ($payload['userPromptTemplate'] ?? ''));

        if ('' === $systemPrompt) {
            return $this->json(['ok' => false, 'error' => 'missing_system_prompt'], 400);
        }

        if ('' === $userPromptTemplate) {
            return $this->json(['ok' => false, 'error' => 'missing_user_prompt_template'], 400);
        }

        $updatedPrompt = $this->rootPrompt->createPromptVersion($code, $systemPrompt, $userPromptTemplate, $actor);
        if (null === $updatedPrompt) {
            return $this->json(['ok' => false, 'error' => 'prompt_not_found'], 404);
        }

        return $this->json(['ok' => true, 'prompt' => $updatedPrompt]);
    }
}
