<?php

namespace App\Controller\Api\Workspace;

use App\Entity\Toast;
use App\Workspace\WorkspaceAccess;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

final class CreateItemController extends AbstractController
{
    public function __construct(
        private readonly WorkspaceAccess $workspaceAccess,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    #[Route('/api/workspaces/{id}/items', name: 'api_workspace_item_create', methods: ['POST'])]
    public function __invoke(int $id, Request $request): JsonResponse
    {
        $workspace = $this->workspaceAccess->getWorkspaceOrFail($id);
        $payload = $request->toArray();
        $title = trim((string) ($payload['title'] ?? ''));

        if ('' === $title) {
            return $this->json(['ok' => false, 'error' => 'missing_title'], 400);
        }

        $item = (new Toast())
            ->setWorkspace($workspace)
            ->setAuthor($this->workspaceAccess->getUserOrFail())
            ->setTitle($title)
            ->setDescription(trim((string) ($payload['description'] ?? '')) ?: null);

        $this->entityManager->persist($item);
        $this->entityManager->flush();

        return $this->json(['ok' => true, 'itemId' => $item->getId()]);
    }
}
