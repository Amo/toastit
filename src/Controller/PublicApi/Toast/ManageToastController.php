<?php

namespace App\Controller\PublicApi\Toast;

use App\Entity\Toast;
use App\Repository\ToastRepository;
use App\Workspace\ToastTransferService;
use App\Workspace\WorkspaceAccessService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

final class ManageToastController extends AbstractController
{
    public function __construct(
        private readonly WorkspaceAccessService $workspaceAccess,
        private readonly ToastTransferService $toastTransfer,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    #[Route('/toasts/{id}', name: 'public_api_toast_get', methods: ['GET'])]
    public function getToast(int $id): JsonResponse
    {
        $toast = $this->workspaceAccess->getItemOrFail($id);

        return $this->json([
            'ok' => true,
            'toast' => $this->buildToastPayload($toast),
        ]);
    }

    #[Route('/toasts/{id}/title', name: 'public_api_toast_title_update', methods: ['PATCH'])]
    public function updateTitle(int $id, Request $request): JsonResponse
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
        $title = trim((string) ($payload['title'] ?? ''));

        if ('' === $title) {
            return $this->json(['ok' => false, 'error' => 'missing_title'], 400);
        }

        $toast->setTitle($title);
        $this->entityManager->flush();

        return $this->json([
            'ok' => true,
            'toast' => $this->buildToastPayload($toast),
        ]);
    }

    #[Route('/toasts/{id}/status', name: 'public_api_toast_status_update', methods: ['PATCH'])]
    public function updateStatus(int $id, Request $request): JsonResponse
    {
        $toast = $this->workspaceAccess->getItemOrFail($id);
        $workspace = $toast->getWorkspace();
        $this->workspaceAccess->assertOwner($workspace);
        $this->workspaceAccess->assertMeetingModeIdle($workspace);

        $payload = $request->toArray();
        $status = strtolower(trim((string) ($payload['status'] ?? '')));

        if (!in_array($status, ['new', 'ready', 'toasted', 'discarded'], true)) {
            return $this->json(['ok' => false, 'error' => 'invalid_status'], 400);
        }

        if ('toasted' === $status) {
            if (!$workspace->isSoloWorkspace() || !$toast->isNew()) {
                return $this->json(['ok' => false, 'error' => 'toast_not_allowed'], 400);
            }

            $toast
                ->setStatus(Toast::STATUS_TOASTED)
                ->setIsBoosted(false)
                ->setStatusChangedAt(new \DateTimeImmutable());
        } elseif ('ready' === $status) {
            if ($workspace->isSoloWorkspace()) {
                return $this->json(['ok' => false, 'error' => 'ready_not_allowed_for_solo_workspace'], 400);
            }

            if (!$toast->isNew()) {
                return $this->json(['ok' => false, 'error' => 'toast_not_editable'], 400);
            }

            if (($toast->getOwner()?->getId()) !== $this->workspaceAccess->getUserOrFail()->getId()) {
                return $this->json(['ok' => false, 'error' => 'only_assignee_can_mark_ready'], 403);
            }

            $toast->setStatus(Toast::STATUS_READY);
        } elseif ('discarded' === $status) {
            if ($toast->isToasted()) {
                return $this->json(['ok' => false, 'error' => 'toast_not_editable'], 400);
            }

            $toast
                ->setStatus(Toast::STATUS_DISCARDED)
                ->setIsBoosted(false)
                ->setStatusChangedAt(new \DateTimeImmutable());
        } else {
            if ($toast->isToasted()) {
                return $this->json(['ok' => false, 'error' => 'toast_not_editable'], 400);
            }

            $toast
                ->setStatus(Toast::STATUS_PENDING)
                ->setStatusChangedAt(null);
        }

        $this->entityManager->flush();

        return $this->json([
            'ok' => true,
            'toast' => $this->buildToastPayload($toast),
        ]);
    }

    #[Route('/toasts/{id}/workspace', name: 'public_api_toast_workspace_update', methods: ['PATCH'])]
    public function transferWorkspace(int $id, Request $request): JsonResponse
    {
        $source = $this->workspaceAccess->getItemOrFail($id);
        $sourceWorkspace = $source->getWorkspace();
        $this->workspaceAccess->assertOwner($sourceWorkspace);

        if (!$source->isNew()) {
            return $this->json(['ok' => false, 'error' => 'only_new_toasts_can_be_transferred'], 400);
        }

        $payload = $request->toArray();
        $targetWorkspaceId = is_numeric($payload['workspaceId'] ?? null) ? (int) $payload['workspaceId'] : 0;

        if ($targetWorkspaceId <= 0 || $targetWorkspaceId === $sourceWorkspace->getId()) {
            return $this->json(['ok' => false, 'error' => 'invalid_target_workspace'], 400);
        }

        $targetWorkspace = $this->workspaceAccess->getWorkspaceOrFail($targetWorkspaceId);
        $transferredToast = $this->toastTransfer->transfer($source, $targetWorkspace, $this->workspaceAccess->getUserOrFail());
        $this->entityManager->flush();

        return $this->json([
            'ok' => true,
            'toast' => $this->buildToastPayload($transferredToast),
        ]);
    }

    private function buildToastPayload(Toast $toast): array
    {
        return [
            'id' => $toast->getId(),
            'workspaceId' => $toast->getWorkspace()->getId(),
            'title' => $toast->getTitle(),
            'description' => $toast->getDescription(),
            'status' => $toast->getStatus(),
            'publicStatus' => $this->resolvePublicStatus($toast),
            'isBoosted' => $toast->isBoosted(),
            'dueOn' => $toast->getDueAt()?->format('Y-m-d'),
            'createdAt' => $toast->getCreatedAt()->format(\DateTimeInterface::ATOM),
            'assigneeEmail' => $toast->getOwner()?->getPublicEmail(),
            'author' => [
                'id' => $toast->getAuthor()->getId(),
                'displayName' => $toast->getAuthor()->getDisplayName(),
                'email' => $toast->getAuthor()->getPublicEmail(),
            ],
            'voteCount' => $toast->getVoteCount(),
        ];
    }

    private function resolvePublicStatus(Toast $toast): string
    {
        if ($toast->isVetoed()) {
            return ToastRepository::PUBLIC_STATUS_DISCARDED;
        }

        if ($toast->isToasted()) {
            return ToastRepository::PUBLIC_STATUS_TOASTED;
        }

        if ($toast->isReady()) {
            return ToastRepository::PUBLIC_STATUS_READY;
        }

        return ToastRepository::PUBLIC_STATUS_NEW;
    }
}
