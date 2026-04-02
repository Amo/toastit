<?php

namespace App\Controller\Api\Profile;

use App\Profile\AccountDeletionService;
use App\Entity\LoginChallenge;
use App\Security\LoginChallengeService;
use App\Workspace\WorkspaceAccessService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

final class DeleteController extends AbstractController
{
    public function __construct(
        private readonly WorkspaceAccessService $workspaceAccess,
        private readonly AccountDeletionService $accountDeletionService,
        private readonly LoginChallengeService $loginChallengeService,
    ) {
    }

    #[Route('/api/profile', name: 'api_profile_delete', methods: ['DELETE'])]
    public function __invoke(Request $request): JsonResponse
    {
        $payload = $request->toArray();
        $user = $this->workspaceAccess->getUserOrFail();

        if (($payload['confirmation'] ?? null) !== 'DELETE') {
            return $this->json(['ok' => false, 'error' => 'invalid_confirmation'], 400);
        }

        $otp = trim((string) ($payload['otp'] ?? ''));

        if (null === $this->loginChallengeService->consumeForUserByCode($user, $otp, LoginChallenge::PURPOSE_DELETE_ACCOUNT)) {
            return $this->json(['ok' => false, 'error' => 'invalid_otp'], 401);
        }

        $this->accountDeletionService->delete($user);

        return $this->json(['ok' => true]);
    }
}
