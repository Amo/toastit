<?php

namespace App\Controller\Api\Auth;

use App\Entity\LoginChallenge;
use App\Security\ApiRefreshTokenService;
use App\Security\JwtTokenService;
use App\Security\LoginChallengeService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

final class VerifyOtpController extends AbstractController
{
    public function __construct(
        private readonly LoginChallengeService $loginChallengeManager,
        private readonly JwtTokenService $jwtTokenManager,
        private readonly ApiRefreshTokenService $refreshTokenManager,
    ) {
    }

    #[Route('/api/auth/verify-otp', name: 'api_auth_verify_otp', methods: ['POST'])]
    public function __invoke(Request $request): JsonResponse
    {
        $payload = $request->toArray();
        $email = trim((string) ($payload['email'] ?? ''));
        $code = trim((string) ($payload['code'] ?? ''));
        $challenge = $this->loginChallengeManager->consumeByCode($email, $code, LoginChallenge::PURPOSE_LOGIN);

        if (null === $challenge) {
            return $this->json(['ok' => false, 'error' => 'invalid_otp'], 401);
        }

        $user = $challenge->getUser();
        $now = new \DateTimeImmutable();

        if (!$user->hasPin()) {
            return $this->json([
                'ok' => true,
                'requiresPinSetup' => true,
                'pinSetupToken' => $this->jwtTokenManager->createPinSetupToken($user, $now),
            ]);
        }

        return $this->json([
            'ok' => true,
            'accessToken' => $this->jwtTokenManager->createAccessToken($user, $now),
            'refreshToken' => $this->refreshTokenManager->issue($user, $now),
        ]);
    }
}
