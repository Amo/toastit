<?php

namespace App\Controller\Api\Auth;

use App\Api\AuthPayloadBuilder;
use App\Security\ApiRefreshTokenService;
use App\Security\JwtTokenService;
use App\Security\PinService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

final class UnlockPinController extends AbstractController
{
    public function __construct(
        private readonly JwtTokenService $jwtTokenManager,
        private readonly ApiRefreshTokenService $refreshTokenService,
        private readonly PinService $pinService,
        private readonly AuthPayloadBuilder $authPayloadBuilder,
    ) {
    }

    #[Route('/api/auth/pin/unlock', name: 'api_auth_pin_unlock', methods: ['POST'])]
    public function __invoke(Request $request): JsonResponse
    {
        $payload = $request->toArray();
        $pin = (string) ($payload['pin'] ?? '');
        $refreshTokenValue = (string) ($payload['refreshToken'] ?? '');
        $pinUnlockToken = (string) ($payload['pinUnlockToken'] ?? '');
        $now = new \DateTimeImmutable();

        if ('' !== $pinUnlockToken) {
            $tokenPayload = $this->jwtTokenManager->decode($pinUnlockToken);

            if (!is_array($tokenPayload) || ($tokenPayload['typ'] ?? null) !== 'pin_unlock' || !isset($tokenPayload['sub'])) {
                return $this->json(['ok' => false, 'error' => 'invalid_pin_unlock_token'], 401);
            }

            $user = $this->refreshTokenService->getUserRepository()->find((int) $tokenPayload['sub']);

            if (null === $user || !$this->pinService->verifyPin($user, $pin)) {
                return $this->json(['ok' => false, 'error' => 'invalid_pin'], 401);
            }

            $refreshToken = $this->refreshTokenService->issue($user, $now);

            return $this->json($this->authPayloadBuilder->buildAuthenticated(
                $user,
                $this->jwtTokenManager->createAccessToken($user, $now),
                $refreshToken,
                $now->getTimestamp() + 900,
            ));
        }

        if ('' !== $refreshTokenValue) {
            $validation = $this->refreshTokenService->validate($refreshTokenValue, $now);

            if (null !== $validation->error || null === $validation->refreshToken) {
                return $this->json(['ok' => false, 'error' => $validation->error ?? 'invalid_refresh_token'], 401);
            }

            $refreshToken = $validation->refreshToken;
            $user = $refreshToken->getUser();

            if (!$this->pinService->verifyPin($user, $pin)) {
                return $this->json(['ok' => false, 'error' => 'invalid_pin'], 401);
            }

            $this->refreshTokenService->markUsed($refreshToken, $now);

            return $this->json($this->authPayloadBuilder->buildAuthenticated(
                $user,
                $this->jwtTokenManager->createAccessToken($user, $now),
                $refreshTokenValue,
                $now->getTimestamp() + 900,
            ));
        }

        return $this->json(['ok' => false, 'error' => 'missing_unlock_context'], 401);
    }
}
