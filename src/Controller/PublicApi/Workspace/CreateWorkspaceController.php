<?php

namespace App\Controller\PublicApi\Workspace;

use App\Workspace\WorkspaceAccessService;
use App\Workspace\WorkspaceCreationService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

final class CreateWorkspaceController extends AbstractController
{
    public function __construct(
        private readonly WorkspaceAccessService $workspaceAccess,
        private readonly WorkspaceCreationService $workspaceCreation,
    ) {
    }

    #[Route('/workspaces', name: 'public_api_workspace_create', methods: ['POST'])]
    public function __invoke(Request $request): JsonResponse
    {
        $payload = $request->toArray();
        $name = trim((string) ($payload['name'] ?? ''));

        if ('' === $name) {
            return $this->json(['ok' => false, 'error' => 'invalid_name'], 400);
        }

        $user = $this->workspaceAccess->getUserOrFail();
        $workspace = $this->workspaceCreation->createOwnedWorkspace($user, $name);
        $this->workspaceCreation->flush();

        return $this->json([
            'ok' => true,
            'workspace' => [
                'id' => $workspace->getId(),
                'name' => $workspace->getName(),
                'isDefault' => $workspace->isDefault(),
                'isSoloWorkspace' => $workspace->isSoloWorkspace(),
                'meetingMode' => $workspace->getMeetingMode(),
                'memberCount' => $workspace->getMemberships()->count(),
                'currentUserIsOwner' => $workspace->isOwnedBy($user),
                'createdAt' => $workspace->getCreatedAt()->format(\DateTimeInterface::ATOM),
            ],
        ], 201);
    }
}
