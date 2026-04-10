<?php

namespace App\Controller\PublicApi\Toast;

use App\Workspace\WorkspaceAccessService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

final class UpdateToastDueDateController extends AbstractController
{
    public function __construct(
        private readonly WorkspaceAccessService $workspaceAccess,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    #[Route('/toasts/{id}/due-date', name: 'public_api_toast_due_date_update', methods: ['PATCH'])]
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
        if (!array_key_exists('dueOn', $payload)) {
            return $this->json(['ok' => false, 'error' => 'missing_due_on'], 400);
        }

        $dueOn = trim((string) ($payload['dueOn'] ?? ''));
        $dueAt = null;

        if ('' !== $dueOn) {
            try {
                $dueAt = new \DateTimeImmutable($dueOn);
            } catch (\Exception) {
                return $this->json(['ok' => false, 'error' => 'invalid_due_on'], 400);
            }
        }

        $toast->setDueAt($dueAt);
        $this->entityManager->flush();

        return $this->json([
            'ok' => true,
            'toast' => [
                'id' => $toast->getId(),
                'dueOn' => $toast->getDueAt()?->format('Y-m-d'),
            ],
        ]);
    }
}
