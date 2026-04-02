<?php

namespace App\Controller\Api\Profile;

use App\Entity\LoginChallenge;
use App\Mailer\TransactionalMailer;
use App\Security\LoginChallengeService;
use App\Workspace\WorkspaceAccessService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

final class RequestDeletionOtpController extends AbstractController
{
    public function __construct(
        private readonly WorkspaceAccessService $workspaceAccess,
        private readonly LoginChallengeService $loginChallengeService,
        private readonly TransactionalMailer $transactionalMailer,
    ) {
    }

    #[Route('/api/profile/delete-request', name: 'api_profile_delete_request', methods: ['POST'])]
    public function __invoke(): JsonResponse
    {
        $user = $this->workspaceAccess->getUserOrFail();
        $createdChallenge = $this->loginChallengeService->issueChallenge($user, LoginChallenge::PURPOSE_DELETE_ACCOUNT);
        $this->transactionalMailer->sendDeleteAccountChallenge($user, $createdChallenge->challenge);

        return $this->json(['ok' => true]);
    }
}
