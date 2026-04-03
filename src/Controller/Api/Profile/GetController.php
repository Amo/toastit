<?php

namespace App\Controller\Api\Profile;

use App\Api\ProfilePayloadBuilder;
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
        private readonly ProfilePayloadBuilder $profilePayloadBuilder,
    ) {
    }

    #[Route('/api/profile', name: 'api_profile_get', methods: ['GET'])]
    public function __invoke(): JsonResponse
    {
        $user = $this->workspaceAccess->getUserOrFail();

        return $this->json($this->profilePayloadBuilder->buildProfile(
            $user,
            $this->workspaceRepository->findDeletedOwnedByUser($user),
        ));
    }
}
