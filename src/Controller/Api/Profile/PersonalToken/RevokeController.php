<?php

namespace App\Controller\Api\Profile\PersonalToken;

use App\Security\PersonalAccessTokenService;
use App\Workspace\WorkspaceAccessService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

final class RevokeController extends AbstractController
{
    public function __construct(
        private readonly WorkspaceAccessService $workspaceAccess,
        private readonly PersonalAccessTokenService $personalAccessTokenService,
    ) {
    }

    #[Route('/api/profile/personal-tokens/{id}', name: 'api_profile_personal_token_revoke', methods: ['DELETE'])]
    public function __invoke(int $id): JsonResponse
    {
        $token = $this->personalAccessTokenService->findOwnedByUserAndId(
            $this->workspaceAccess->getUserOrFail(),
            $id,
        );

        if (null === $token) {
            return $this->json(['ok' => false, 'error' => 'token_not_found'], 404);
        }

        $this->personalAccessTokenService->revoke($token);

        return $this->json(['ok' => true]);
    }
}

