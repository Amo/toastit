<?php

namespace App\Controller\Api\Profile;

use App\Workspace\WorkspaceAccessService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

final class GetController extends AbstractController
{
    public function __construct(
        private readonly WorkspaceAccessService $workspaceAccess,
    ) {
    }

    #[Route('/api/profile', name: 'api_profile_get', methods: ['GET'])]
    public function __invoke(): JsonResponse
    {
        $user = $this->workspaceAccess->getUserOrFail();

        return $this->json([
            'user' => [
                'id' => $user->getId(),
                'email' => $user->getEmail(),
                'displayName' => $user->getDisplayName(),
                'firstName' => $user->getFirstName(),
                'lastName' => $user->getLastName(),
                'initials' => $user->getInitials(),
                'gravatarUrl' => $user->getGravatarUrl(),
            ],
        ]);
    }
}
