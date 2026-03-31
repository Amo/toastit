<?php

namespace App\Controller\Api\Auth;

use App\Entity\LoginChallenge;
use App\Mailer\TransactionalMailer;
use App\Security\LoginChallengeManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class RequestOtpController extends AbstractController
{
    public function __construct(
        private readonly LoginChallengeManager $loginChallengeManager,
        private readonly TransactionalMailer $transactionalMailer,
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
    }

    #[Route('/api/auth/request-otp', name: 'api_auth_request_otp', methods: ['POST'])]
    public function __invoke(Request $request): JsonResponse
    {
        $payload = $request->toArray();
        $email = trim((string) ($payload['email'] ?? ''));

        if ('' === $email || false === filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $this->json(['ok' => false, 'error' => 'invalid_email'], 400);
        }

        $user = $this->loginChallengeManager->getOrCreateUser($email);
        $createdChallenge = $this->loginChallengeManager->issueChallenge($user, LoginChallenge::PURPOSE_LOGIN);
        $magicLink = $this->urlGenerator->generate('app_auth_magic', [
            'selector' => $createdChallenge->challenge->getSelector(),
            'token' => $createdChallenge->plainToken,
        ], UrlGeneratorInterface::ABSOLUTE_URL);

        $this->transactionalMailer->sendLoginChallenge($user, $createdChallenge->challenge, $magicLink);

        return $this->json(['ok' => true]);
    }
}
