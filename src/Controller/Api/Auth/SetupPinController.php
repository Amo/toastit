<?php

namespace App\Controller\Api\Auth;

use App\Api\AuthPayloadBuilder;
use App\Repository\UserRepository;
use App\Security\ApiRefreshTokenService;
use App\Security\JwtTokenService;
use App\Security\PinService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

final class SetupPinController extends AbstractController
{
    public function __construct(
        private readonly JwtTokenService $jwtTokenManager,
        private readonly UserRepository $userRepository,
        private readonly PinService $pinManager,
        private readonly ApiRefreshTokenService $refreshTokenManager,
        private readonly AuthPayloadBuilder $authPayloadBuilder,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    #[Route('/api/auth/pin/setup', name: 'api_auth_pin_setup', methods: ['POST'])]
    public function __invoke(Request $request): JsonResponse
    {
        $payload = $request->toArray();
        $tokenPayload = $this->jwtTokenManager->decode((string) ($payload['pinSetupToken'] ?? ''));

        if (!is_array($tokenPayload) || ($tokenPayload['typ'] ?? null) !== 'pin_setup' || !isset($tokenPayload['sub'])) {
            return $this->json(['ok' => false, 'error' => 'invalid_pin_setup_token'], 401);
        }

        $user = $this->userRepository->find((int) $tokenPayload['sub']);
        $pin = (string) ($payload['pin'] ?? '');
        $pinConfirmation = (string) ($payload['pinConfirmation'] ?? '');

        if (null === $user) {
            return $this->json(['ok' => false, 'error' => 'unknown_user'], 404);
        }

        if (!preg_match('/^\d{4}$/', $pin) || $pin !== $pinConfirmation) {
            return $this->json(['ok' => false, 'error' => 'invalid_pin'], 400);
        }

        $user
            ->setPinHash($this->pinManager->hashPin($user, $pin))
            ->setPinSetAt(new \DateTimeImmutable());
        $this->entityManager->flush();

        $now = new \DateTimeImmutable();

        return $this->json($this->authPayloadBuilder->buildAuthenticated(
            $user,
            $this->jwtTokenManager->createAccessToken($user, $now),
            $this->refreshTokenManager->issue($user, $now),
            $now->getTimestamp() + 900,
        ));
    }
}
