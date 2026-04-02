<?php

namespace App\Controller\Api\Auth;

use App\Api\AuthPayloadBuilder;
use App\Security\ApiRefreshTokenService;
use App\Security\JwtTokenService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

final class RefreshController extends AbstractController
{
    public function __construct(
        private readonly ApiRefreshTokenService $refreshTokenManager,
        private readonly JwtTokenService $jwtTokenManager,
        private readonly AuthPayloadBuilder $authPayloadBuilder,
    ) {
    }

    #[Route('/api/auth/refresh', name: 'api_auth_refresh', methods: ['POST'])]
    public function __invoke(Request $request): JsonResponse
    {
        $payload = $request->toArray();
        $refreshTokenValue = (string) ($payload['refreshToken'] ?? '');
        $now = new \DateTimeImmutable();
        $validation = $this->refreshTokenManager->validate($refreshTokenValue, $now);

        if (null !== $validation->error) {
            return $this->json(['ok' => false, 'error' => $validation->error], 401);
        }

        $refreshToken = $validation->refreshToken;
        $user = $refreshToken->getUser();

        $this->refreshTokenManager->markUsed($refreshToken, $now);

        return $this->json($this->authPayloadBuilder->buildAuthenticated(
            $user,
            $this->jwtTokenManager->createAccessToken($user, $now),
            $refreshTokenValue,
            $now->getTimestamp() + 900,
        ));
    }
}
