<?php

namespace App\Controller\Api\Workspace;

use App\Workspace\WorkspaceAccessService;
use App\Workspace\WorkspaceBackgroundStorageService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Attribute\Route;

final class GetBackgroundController extends AbstractController
{
    public function __construct(
        private readonly WorkspaceAccessService $workspaceAccess,
        private readonly WorkspaceBackgroundStorageService $backgroundStorage,
    ) {
    }

    #[Route('/api/workspaces/{id}/background', name: 'api_workspace_background_get', methods: ['GET'])]
    public function __invoke(int $id): StreamedResponse
    {
        $workspace = $this->workspaceAccess->getWorkspaceOrFail($id);
        $backgroundPath = $workspace->getPermalinkBackgroundUrl();

        if (!$this->backgroundStorage->isStoredPath($backgroundPath)) {
            throw $this->createNotFoundException();
        }

        $stream = $this->backgroundStorage->readStream($backgroundPath);
        $mimeType = $this->backgroundStorage->resolveMimeType($backgroundPath);

        return new StreamedResponse(static function () use ($stream): void {
            fpassthru($stream);

            if (is_resource($stream)) {
                fclose($stream);
            }
        }, headers: [
            'Content-Type' => $mimeType,
            'Cache-Control' => 'private, max-age=3600',
        ]);
    }
}
