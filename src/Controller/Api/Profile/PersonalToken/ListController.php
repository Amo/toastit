<?php

namespace App\Controller\Api\Profile\PersonalToken;

use App\Entity\PersonalAccessToken;
use App\Security\PersonalAccessTokenService;
use App\Workspace\WorkspaceAccessService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

final class ListController extends AbstractController
{
    public function __construct(
        private readonly WorkspaceAccessService $workspaceAccess,
        private readonly PersonalAccessTokenService $personalAccessTokenService,
    ) {
    }

    #[Route('/api/profile/personal-tokens', name: 'api_profile_personal_token_list', methods: ['GET'])]
    public function __invoke(): JsonResponse
    {
        $tokens = $this->personalAccessTokenService->listOwnedByUser($this->workspaceAccess->getUserOrFail());

        return $this->json([
            'ok' => true,
            'tokens' => array_map(static fn (PersonalAccessToken $token): array => [
                'id' => $token->getId(),
                'name' => $token->getName(),
                'selector' => $token->getSelector(),
                'createdAt' => $token->getCreatedAt()->format(\DateTimeInterface::ATOM),
                'lastUsedAt' => $token->getLastUsedAt()?->format(\DateTimeInterface::ATOM),
                'expiresAt' => $token->getExpiresAt()?->format(\DateTimeInterface::ATOM),
                'revokedAt' => $token->getRevokedAt()?->format(\DateTimeInterface::ATOM),
            ], $tokens),
        ]);
    }
}

