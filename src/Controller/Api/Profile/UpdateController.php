<?php

namespace App\Controller\Api\Profile;

use App\Workspace\WorkspaceAccess;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

final class UpdateController extends AbstractController
{
    public function __construct(
        private readonly WorkspaceAccess $workspaceAccess,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    #[Route('/api/profile', name: 'api_profile_update', methods: ['PUT'])]
    public function __invoke(Request $request): JsonResponse
    {
        $user = $this->workspaceAccess->getUserOrFail();
        $payload = $request->toArray();

        $user
            ->setFirstName((string) ($payload['firstName'] ?? '') ?: null)
            ->setLastName((string) ($payload['lastName'] ?? '') ?: null);

        $this->entityManager->flush();

        return $this->json([
            'ok' => true,
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
