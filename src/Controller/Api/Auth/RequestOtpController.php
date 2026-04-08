<?php

namespace App\Controller\Api\Auth;

use App\Entity\LoginChallenge;
use App\Mailer\TransactionalMailer;
use App\Routing\AppUrlGenerator;
use App\Security\LoginChallengeService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

final class RequestOtpController extends AbstractController
{
    public function __construct(
        private readonly LoginChallengeService $loginChallengeManager,
        private readonly TransactionalMailer $transactionalMailer,
        private readonly AppUrlGenerator $appUrlGenerator,
    ) {
    }

    #[Route('/api/auth/request-otp', name: 'api_auth_request_otp', methods: ['POST'])]
    public function __invoke(Request $request): JsonResponse
    {
        $payload = $request->toArray();
        $email = trim((string) ($payload['email'] ?? ''));
        $purpose = (string) ($payload['purpose'] ?? LoginChallenge::PURPOSE_LOGIN);

        if ('' === $email || false === filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $this->json(['ok' => false, 'error' => 'invalid_email'], 400);
        }

        $user = $this->loginChallengeManager->getOrCreateUser($email);
        $createdChallenge = $this->loginChallengeManager->issueChallenge($user, $purpose);
        $magicLink = $this->appUrlGenerator->spaPath(sprintf(
            'connexion/magic/%s/%s',
            $createdChallenge->challenge->getSelector(),
            $createdChallenge->plainToken,
        ));

        $this->transactionalMailer->sendLoginChallenge($user, $createdChallenge->challenge, $magicLink);

        return $this->json(['ok' => true]);
    }
}
