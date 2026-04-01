<?php

namespace App\Controller\Api\Workspace;

use App\Entity\Workspace;
use App\Workspace\WorkspaceAccess;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

final class UpdateSettingsController extends AbstractController
{
    public function __construct(
        private readonly WorkspaceAccess $workspaceAccess,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    #[Route('/api/workspaces/{id}/settings', name: 'api_workspace_settings_update', methods: ['POST'])]
    public function __invoke(int $id, Request $request): JsonResponse
    {
        $workspace = $this->workspaceAccess->getWorkspaceOrFail($id);
        $this->workspaceAccess->assertOwner($workspace);

        $payload = $request->toArray();
        $name = trim((string) ($payload['name'] ?? ''));

        if ('' === $name) {
            return $this->json(['ok' => false, 'error' => 'missing_name'], 400);
        }

        $isSoloWorkspace = (bool) ($payload['isSoloWorkspace'] ?? false);

        if ($isSoloWorkspace && $workspace->isMeetingLive()) {
            $workspace->stopMeetingMode($this->workspaceAccess->getUserOrFail());
        }

        $workspace->setName($name);
        $workspace->setDefaultDuePreset((string) ($payload['defaultDuePreset'] ?? Workspace::DEFAULT_DUE_NEXT_WEEK));
        $workspace->setPermalinkBackgroundUrl(isset($payload['permalinkBackgroundUrl']) ? (string) $payload['permalinkBackgroundUrl'] : null);
        $workspace->setIsSoloWorkspace($isSoloWorkspace);

        $this->entityManager->flush();

        return $this->json([
            'ok' => true,
            'name' => $workspace->getName(),
            'defaultDuePreset' => $workspace->getDefaultDuePreset(),
            'permalinkBackgroundUrl' => $workspace->getPermalinkBackgroundUrl(),
            'isSoloWorkspace' => $workspace->isSoloWorkspace(),
        ]);
    }
}
