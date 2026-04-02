<?php

namespace App\Controller\Api\Item;

use App\Entity\Toast;
use App\Workspace\WorkspaceAccessService;
use App\Workspace\WorkspaceWorkflowService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

final class UpdateDiscussionController extends AbstractController
{
    public function __construct(
        private readonly WorkspaceAccessService $workspaceAccess,
        private readonly WorkspaceWorkflowService $workspaceWorkflow,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    #[Route('/api/items/{id}/discussion', name: 'api_item_discussion_update', methods: ['POST'])]
    public function __invoke(int $id, Request $request): JsonResponse
    {
        $item = $this->workspaceAccess->getItemOrFail($id);
        $workspace = $item->getWorkspace();
        $this->workspaceAccess->assertOwner($workspace);
        $this->workspaceAccess->assertMeetingModeActive($workspace);

        $payload = $request->toArray();
        $ownerId = is_numeric($payload['ownerId'] ?? null) ? (int) $payload['ownerId'] : 0;
        $owner = $this->workspaceWorkflow->findWorkspaceInviteeById($workspace, $ownerId);
        $dueAt = null;

        if (!empty($payload['dueOn'])) {
            try {
                $dueAt = new \DateTimeImmutable((string) $payload['dueOn']);
            } catch (\Exception) {
                return $this->json(['ok' => false, 'error' => 'invalid_due_on'], 400);
            }
        }

        $followUpItems = [];
        foreach (($payload['followUpItems'] ?? []) as $followUpItem) {
            $title = trim((string) ($followUpItem['title'] ?? ''));

            if ('' === $title) {
                continue;
            }

            $followUpOwnerId = is_numeric($followUpItem['ownerId'] ?? null) ? (int) $followUpItem['ownerId'] : 0;
            $followUpOwner = $this->workspaceWorkflow->findWorkspaceInviteeById($workspace, $followUpOwnerId);
            $followUpDueOn = trim((string) ($followUpItem['dueOn'] ?? ''));

            if ('' !== $followUpDueOn) {
                try {
                    new \DateTimeImmutable($followUpDueOn);
                } catch (\Exception) {
                    return $this->json(['ok' => false, 'error' => 'invalid_follow_up_due_on'], 400);
                }
            }

            $followUpItems[] = [
                'title' => $title,
                'ownerId' => $followUpOwner?->getId(),
                'dueOn' => '' !== $followUpDueOn ? $followUpDueOn : null,
            ];
        }

        $item
            ->setDiscussionStatus(Toast::DISCUSSION_TREATED)
            ->setDiscussionNotes(trim((string) ($payload['discussionNotes'] ?? '')) ?: null)
            ->setOwner($owner)
            ->setDueAt($dueAt)
            ->setStatusChangedAt(new \DateTimeImmutable());

        foreach ($followUpItems as $followUpItem) {
            $followUpOwner = $this->workspaceWorkflow->findWorkspaceInviteeById($workspace, (int) ($followUpItem['ownerId'] ?? 0));
            $followUpDueAt = !empty($followUpItem['dueOn']) ? new \DateTimeImmutable((string) $followUpItem['dueOn']) : null;

            if ($this->workspaceWorkflow->hasFollowUp($item, $followUpItem['title'], $followUpOwner, $followUpDueAt)) {
                continue;
            }

            $nextItem = (new Toast())
                ->setWorkspace($workspace)
                ->setAuthor($this->workspaceAccess->getUserOrFail())
                ->setTitle($followUpItem['title'])
                ->setOwner($followUpOwner)
                ->setDueAt($followUpDueAt)
                ->setPreviousItem($item)
                ->setDescription(sprintf('Follow-up created from "%s".', $item->getTitle()));

            $this->entityManager->persist($nextItem);
        }

        $this->entityManager->flush();

        return $this->json(['ok' => true]);
    }
}
