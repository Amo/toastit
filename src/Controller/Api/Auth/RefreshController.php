<?php

namespace App\Controller\Api\Auth;

use App\Security\ApiRefreshTokenManager;
use App\Security\JwtTokenManager;
use App\Security\PinManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

final class RefreshController extends AbstractController
{
    public function __construct(
        private readonly ApiRefreshTokenManager $refreshTokenManager,
        private readonly JwtTokenManager $jwtTokenManager,
        private readonly PinManager $pinManager,
    ) {
    }

    #[Route('/api/auth/refresh', name: 'api_auth_refresh', methods: ['POST'])]
    public function __invoke(Request $request): JsonResponse
    {
        $payload = $request->toArray();
        $refreshTokenValue = (string) ($payload['refreshToken'] ?? '');
        $pin = (string) ($payload['pin'] ?? '');
        $now = new \DateTimeImmutable();
        $validation = $this->refreshTokenManager->validate($refreshTokenValue, $now);

        if (null !== $validation->error) {
            return $this->json(['ok' => false, 'error' => $validation->error], 401);
        }

        $refreshToken = $validation->refreshToken;
        $user = $refreshToken->getUser();

        if (!$this->pinManager->verifyPin($user, $pin)) {
            return $this->json(['ok' => false, 'error' => 'invalid_pin'], 401);
        }

        $this->refreshTokenManager->markUsed($refreshToken, $now);

        return $this->json([
            'ok' => true,
            'accessToken' => $this->jwtTokenManager->createAccessToken($user, $now),
            'refreshToken' => $refreshTokenValue,
        ]);
    }
}
