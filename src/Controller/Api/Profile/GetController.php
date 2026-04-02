<?php

namespace App\Controller\Api\Profile;

use App\Workspace\WorkspaceAccessService;
use App\Repository\WorkspaceRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

final class GetController extends AbstractController
{
    public function __construct(
        private readonly WorkspaceAccessService $workspaceAccess,
        private readonly WorkspaceRepository $workspaceRepository,
    ) {
    }

    #[Route('/api/profile', name: 'api_profile_get', methods: ['GET'])]
    public function __invoke(): JsonResponse
    {
        $user = $this->workspaceAccess->getUserOrFail();

        return $this->json([
            'user' => [
                'id' => $user->getId(),
                'email' => $user->getPublicEmail(),
                'displayName' => $user->getDisplayName(),
                'firstName' => $user->getFirstName(),
                'lastName' => $user->getLastName(),
                'initials' => $user->getInitials(),
                'gravatarUrl' => $user->getGravatarUrl(),
            ],
            'deletedWorkspaces' => array_map(static fn ($workspace): array => [
                'id' => $workspace->getId(),
                'name' => $workspace->getName(),
                'deletedAt' => $workspace->getDeletedAt()?->format(\DateTimeInterface::ATOM),
                'deletedAtDisplay' => $workspace->getDeletedAt()?->format('d/m/Y H:i'),
                'isSoloWorkspace' => $workspace->isSoloWorkspace(),
            ], $this->workspaceRepository->findDeletedOwnedByUser($user)),
        ]);
    }
}
