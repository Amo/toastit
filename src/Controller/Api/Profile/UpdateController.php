<?php

namespace App\Controller\Api\Profile;

use App\Api\ProfilePayloadBuilder;
use App\Workspace\WorkspaceAccessService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

final class UpdateController extends AbstractController
{
    public function __construct(
        private readonly WorkspaceAccessService $workspaceAccess,
        private readonly EntityManagerInterface $entityManager,
        private readonly ProfilePayloadBuilder $profilePayloadBuilder,
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
            'user' => $this->profilePayloadBuilder->buildUser($user),
        ]);
    }
}
