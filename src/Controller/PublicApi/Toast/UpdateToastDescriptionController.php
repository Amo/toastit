<?php

namespace App\Controller\PublicApi\Toast;

use App\Workspace\WorkspaceAccessService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

final class UpdateToastDescriptionController extends AbstractController
{
    public function __construct(
        private readonly WorkspaceAccessService $workspaceAccess,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    #[Route('/toasts/{id}/description', name: 'public_api_toast_description_update', methods: ['PATCH'])]
    public function __invoke(int $id, Request $request): JsonResponse
    {
        $toast = $this->workspaceAccess->getItemOrFail($id);
        $workspace = $toast->getWorkspace();
        $currentUser = $this->workspaceAccess->getUserOrFail();

        if (!$toast->isNew()) {
            return $this->json(['ok' => false, 'error' => 'toast_not_editable'], 400);
        }

        if (!$workspace->isOwnedBy($currentUser) && $toast->getAuthor()->getId() !== $currentUser->getId()) {
            return $this->json(['ok' => false, 'error' => 'forbidden'], 403);
        }

        $payload = $request->toArray();
        if (!array_key_exists('description', $payload)) {
            return $this->json(['ok' => false, 'error' => 'missing_description'], 400);
        }

        $description = trim((string) ($payload['description'] ?? ''));
        $toast->setDescription('' !== $description ? $description : null);
        $this->entityManager->flush();

        return $this->json([
            'ok' => true,
            'toast' => [
                'id' => $toast->getId(),
                'description' => $toast->getDescription(),
            ],
        ]);
    }
}
