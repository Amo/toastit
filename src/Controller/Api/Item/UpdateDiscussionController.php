<?php

namespace App\Controller\Api\Item;

use App\Entity\ParkingLotItem;
use App\Workspace\MeetingWorkflow;
use App\Workspace\WorkspaceAccess;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

final class UpdateDiscussionController extends AbstractController
{
    public function __construct(
        private readonly WorkspaceAccess $workspaceAccess,
        private readonly MeetingWorkflow $meetingWorkflow,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    #[Route('/api/items/{id}/discussion', name: 'api_item_discussion_update', methods: ['POST'])]
    public function __invoke(int $id, Request $request): JsonResponse
    {
        $item = $this->workspaceAccess->getItemOrFail($id);
        $meeting = $item->getMeeting();
        $this->workspaceAccess->assertOrganizer($meeting);
        $this->workspaceAccess->assertMeetingEditable($meeting);

        $payload = $request->toArray();
        $discussionStatus = trim((string) ($payload['discussionStatus'] ?? ''));
        $allowedStatuses = [
            ParkingLotItem::DISCUSSION_PENDING,
            ParkingLotItem::DISCUSSION_TREATED,
            ParkingLotItem::DISCUSSION_POSTPONED,
        ];

        if (!in_array($discussionStatus, $allowedStatuses, true)) {
            $discussionStatus = ParkingLotItem::DISCUSSION_PENDING;
        }

        $ownerId = is_numeric($payload['ownerId'] ?? null) ? (int) $payload['ownerId'] : 0;
        $owner = $this->meetingWorkflow->findMeetingInviteeById($meeting, $ownerId);
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
            $followUpOwner = $this->meetingWorkflow->findMeetingInviteeById($meeting, $followUpOwnerId);
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
            ->setDiscussionStatus($discussionStatus)
            ->setDiscussionNotes(trim((string) ($payload['discussionNotes'] ?? '')) ?: null)
            ->setFollowUp(0 !== count($followUpItems) ? implode("\n", array_map(static fn (array $followUpItem): string => $followUpItem['title'], $followUpItems)) : null)
            ->setFollowUpItems($followUpItems)
            ->setOwner($owner)
            ->setDueAt($dueAt);

        $this->entityManager->flush();

        return $this->json(['ok' => true]);
    }
}
