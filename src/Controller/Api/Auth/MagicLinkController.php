<?php

namespace App\Controller\Api\Auth;

use App\Api\AuthPayloadBuilder;
use App\Entity\LoginChallenge;
use App\Security\JwtTokenService;
use App\Security\LoginChallengeService;
use App\Security\RecaptchaVerifierService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

final class MagicLinkController extends AbstractController
{
    public function __construct(
        private readonly LoginChallengeService $loginChallengeManager,
        private readonly JwtTokenService $jwtTokenManager,
        private readonly AuthPayloadBuilder $authPayloadBuilder,
        private readonly RecaptchaVerifierService $recaptchaVerifier,
    ) {
    }

    #[Route('/api/auth/magic/{selector}/{token}', name: 'api_auth_magic', methods: ['GET'])]
    public function __invoke(string $selector, string $token): JsonResponse
    {
        $challenge = $this->loginChallengeManager->previewByMagicLink($selector, $token);

        if (null === $challenge) {
            return $this->json(['ok' => false, 'error' => 'invalid_magic_link'], 401);
        }

        return $this->buildChallengeResponse($challenge);
    }

    #[Route('/api/auth/magic/{selector}/{token}/consume', name: 'api_auth_magic_consume', methods: ['POST'])]
    public function consume(Request $request, string $selector, string $token): JsonResponse
    {
        $payload = [];

        try {
            $payload = $request->toArray();
        } catch (\Throwable) {
        }

        $recaptchaToken = (string) ($payload['recaptchaToken'] ?? '');
        $recaptchaAction = (string) ($payload['recaptchaAction'] ?? 'magic_link_consume');

        if (!$this->recaptchaVerifier->verifyV3($recaptchaToken, $recaptchaAction, $request->getClientIp())) {
            return $this->json(['ok' => false, 'error' => 'invalid_recaptcha'], 401);
        }

        $challenge = $this->loginChallengeManager->consumeByMagicLink($selector, $token);

        if (null === $challenge) {
            return $this->json(['ok' => false, 'error' => 'invalid_magic_link'], 401);
        }

        return $this->buildChallengeResponse($challenge);
    }

    private function buildChallengeResponse(LoginChallenge $challenge): JsonResponse
    {
        $user = $challenge->getUser();
        $now = new \DateTimeImmutable();

        if (!$user->hasPin() || LoginChallenge::PURPOSE_RESET_PIN === $challenge->getPurpose()) {
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
