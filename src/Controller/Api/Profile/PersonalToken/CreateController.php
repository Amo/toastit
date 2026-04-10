<?php

namespace App\Controller\Api\Profile\PersonalToken;

use App\Security\PersonalAccessTokenService;
use App\Workspace\WorkspaceAccessService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

final class CreateController extends AbstractController
{
    public function __construct(
        private readonly WorkspaceAccessService $workspaceAccess,
        private readonly PersonalAccessTokenService $personalAccessTokenService,
    ) {
    }

    #[Route('/api/profile/personal-tokens', name: 'api_profile_personal_token_create', methods: ['POST'])]
    public function __invoke(Request $request): JsonResponse
    {
        $payload = $request->toArray();
        $name = trim((string) ($payload['name'] ?? ''));

        if ('' === $name) {
            return $this->json(['ok' => false, 'error' => 'missing_name'], 400);
        }

        $expiresAt = null;
        if (array_key_exists('expiresAt', $payload) && null !== $payload['expiresAt'] && '' !== trim((string) $payload['expiresAt'])) {
            try {
                $expiresAt = new \DateTimeImmutable((string) $payload['expiresAt']);
            } catch (\Exception) {
                return $this->json(['ok' => false, 'error' => 'invalid_expires_at'], 400);
            }

            if ($expiresAt <= new \DateTimeImmutable()) {
                return $this->json(['ok' => false, 'error' => 'invalid_expires_at'], 400);
            }
        }

        $issuedToken = $this->personalAccessTokenService->issue(
            $this->workspaceAccess->getUserOrFail(),
            $name,
            $expiresAt,
        );

        return $this->json([
            'ok' => true,
            'token' => [
                'id' => $issuedToken->token->getId(),
                'name' => $issuedToken->token->getName(),
                'selector' => $issuedToken->token->getSelector(),
                'createdAt' => $issuedToken->token->getCreatedAt()->format(\DateTimeInterface::ATOM),
                'expiresAt' => $issuedToken->token->getExpiresAt()?->format(\DateTimeInterface::ATOM),
                'plainTextToken' => $issuedToken->plainTextToken,
            ],
        ], 201);
    }
}

