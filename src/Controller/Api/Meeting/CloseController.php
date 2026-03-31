<?php

namespace App\Controller\Api\Meeting;

use App\Entity\Meeting;
use App\Workspace\MeetingWorkflow;
use App\Workspace\WorkspaceAccess;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

final class CloseController extends AbstractController
{
    public function __construct(
        private readonly WorkspaceAccess $workspaceAccess,
        private readonly MeetingWorkflow $meetingWorkflow,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    #[Route('/api/meetings/{id}/close', name: 'api_meeting_close', methods: ['POST'])]
    public function __invoke(int $id): JsonResponse
    {
        $meeting = $this->workspaceAccess->getMeetingOrFail($id);
        $this->workspaceAccess->assertOrganizer($meeting);

        $nextOccurrence = null;
        if ($meeting->isRecurring()) {
            $nextOccurrence = $this->meetingWorkflow->createNextOccurrenceIfNeeded($meeting);
        }

        if ($meeting->isRecurring() && $nextOccurrence instanceof Meeting) {
            $this->meetingWorkflow->syncFollowUpsToNextOccurrence($meeting, $nextOccurrence);
        }

        $meeting
            ->setStatus(Meeting::STATUS_CLOSED)
            ->setStartedAt($meeting->getStartedAt() ?? new \DateTimeImmutable())
            ->setClosedAt(new \DateTimeImmutable());

        $this->entityManager->flush();

        return $this->json(['ok' => true]);
    }
}
