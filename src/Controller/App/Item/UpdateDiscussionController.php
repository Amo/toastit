<?php

namespace App\Controller\App\Item;

use App\Entity\ParkingLotItem;
use App\Workspace\MeetingWorkflow;
use App\Workspace\WorkspaceAccess;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
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

    #[Route('/app/items/{id}/discussion', name: 'app_item_discussion_update', methods: ['POST'])]
    public function __invoke(int $id, Request $request): RedirectResponse
    {
        $item = $this->workspaceAccess->getItemOrFail($id);
        $meeting = $item->getMeeting();
        $this->workspaceAccess->assertOrganizer($meeting);
        $this->workspaceAccess->assertMeetingEditable($meeting);

        $discussionStatus = trim($request->request->getString('discussion_status'));
        $allowedStatuses = [
            ParkingLotItem::DISCUSSION_PENDING,
            ParkingLotItem::DISCUSSION_TREATED,
            ParkingLotItem::DISCUSSION_POSTPONED,
        ];

        if (!in_array($discussionStatus, $allowedStatuses, true)) {
            $discussionStatus = ParkingLotItem::DISCUSSION_PENDING;
        }

        $ownerIdRaw = trim($request->request->getString('owner_id'));
        $ownerId = ctype_digit($ownerIdRaw) ? (int) $ownerIdRaw : 0;
        $owner = $this->meetingWorkflow->findMeetingInviteeById($meeting, $ownerId);
        $dueAtRaw = trim($request->request->getString('due_at'));
        $dueAt = null;

        if ('' !== $dueAtRaw) {
            try {
                $dueAt = new \DateTimeImmutable($dueAtRaw);
            } catch (\Exception) {
                $this->addFlash('error', 'La date d echeance est invalide.');

                return $this->redirectToRoute('app_meeting_show', ['id' => $meeting->getId()]);
            }
        }

        $followUpTitles = $request->request->all('follow_up_titles');
        $followUpOwnerIds = $request->request->all('follow_up_owner_ids');
        $followUpDueOn = $request->request->all('follow_up_due_on');
        $followUpItems = [];

        foreach ($followUpTitles as $index => $followUpTitleRaw) {
            $title = trim((string) $followUpTitleRaw);

            if ('' === $title) {
                continue;
            }

            $followUpOwnerRaw = trim((string) ($followUpOwnerIds[$index] ?? ''));
            $followUpOwnerId = ctype_digit($followUpOwnerRaw) ? (int) $followUpOwnerRaw : 0;
            $followUpOwner = $this->meetingWorkflow->findMeetingInviteeById($meeting, $followUpOwnerId);
            $followUpDueOnRaw = trim((string) ($followUpDueOn[$index] ?? ''));

            if ('' !== $followUpDueOnRaw) {
                try {
                    new \DateTimeImmutable($followUpDueOnRaw);
                } catch (\Exception) {
                    $this->addFlash('error', 'Une date de suivi est invalide.');

                    return $this->redirectToRoute('app_meeting_show', ['id' => $meeting->getId()]);
                }
            }

            $followUpItems[] = [
                'title' => $title,
                'ownerId' => $followUpOwner?->getId(),
                'dueOn' => '' !== $followUpDueOnRaw ? $followUpDueOnRaw : null,
            ];
        }

        $item
            ->setDiscussionStatus($discussionStatus)
            ->setDiscussionNotes(trim($request->request->getString('discussion_notes')) ?: null)
            ->setFollowUp(0 !== count($followUpItems) ? implode("\n", array_map(static fn (array $followUpItem): string => $followUpItem['title'], $followUpItems)) : null)
            ->setFollowUpItems($followUpItems)
            ->setOwner($owner)
            ->setDueAt($dueAt);

        $this->entityManager->flush();
        $this->addFlash('success', sprintf('Le suivi du sujet "%s" a ete mis a jour.', $item->getTitle()));

        return $this->redirectToRoute('app_meeting_show', ['id' => $meeting->getId()]);
    }
}
