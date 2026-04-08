<?php

namespace App\Controller\Api\Auth;

use App\Api\AuthPayloadBuilder;
use App\Entity\LoginChallenge;
use App\Security\AppEventLogger;
use App\Security\AuthRateLimitService;
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
        private readonly AuthPayloadBuilder $authPayloadBuilder,
        private readonly AuthRateLimitService $authRateLimit,
        private readonly AppEventLogger $eventLogger,
    ) {
    }

    #[Route('/api/auth/verify-otp', name: 'api_auth_verify_otp', methods: ['POST'])]
    public function __invoke(Request $request): JsonResponse
    {
        $payload = $request->toArray();
        $email = trim((string) ($payload['email'] ?? ''));
        $code = trim((string) ($payload['code'] ?? ''));
        $purpose = (string) ($payload['purpose'] ?? LoginChallenge::PURPOSE_LOGIN);

        if (!$this->authRateLimit->allowOtpVerify($request, $email)) {
            $this->eventLogger->log('auth.otp_verified', null, $email, 'verify_otp', 'rate_limited', [
                'purpose' => $purpose,
            ]);
            return $this->json(['ok' => false, 'error' => 'too_many_attempts'], 429);
        }

        $challenge = $this->loginChallengeManager->consumeByCode($email, $code, $purpose);

        if (null === $challenge) {
            $this->eventLogger->log('auth.otp_verified', null, $email, 'verify_otp', 'failed', [
                'purpose' => $purpose,
            ]);
            return $this->json(['ok' => false, 'error' => 'invalid_otp'], 401);
        }

        $user = $challenge->getUser();
        $now = new \DateTimeImmutable();
        $this->eventLogger->log('auth.otp_verified', $user->getId(), $email, 'verify_otp', 'succeeded', [
            'purpose' => $purpose,
        ]);

        if (!$user->hasPin() || LoginChallenge::PURPOSE_RESET_PIN === $purpose) {
            return $this->json([
                'ok' => true,
                'requiresPinSetup' => true,
                'pinSetupToken' => $this->jwtTokenManager->createPinSetupToken($user, $now),
                'user' => $this->authPayloadBuilder->buildUser($user),
            ]);
        }

        return $this->json([
            'ok' => true,
            'requiresPinUnlock' => true,
            'pinUnlockToken' => $this->jwtTokenManager->createPinUnlockToken($user, $now),
            'user' => $this->authPayloadBuilder->buildUser($user),
        ]);
    }
}
