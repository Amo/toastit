<?php

namespace App\Controller\Api\Team;

use App\Entity\Meeting;
use App\Entity\MeetingAttendee;
use App\Workspace\MeetingWorkflow;
use App\Workspace\WorkspaceAccess;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

final class CreateMeetingController extends AbstractController
{
    public function __construct(
        private readonly WorkspaceAccess $workspaceAccess,
        private readonly MeetingWorkflow $meetingWorkflow,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    #[Route('/api/teams/{id}/meetings', name: 'api_team_meeting_create', methods: ['POST'])]
    public function __invoke(int $id, Request $request): JsonResponse
    {
        $team = $this->workspaceAccess->getTeamOrFail($id);
        $payload = $request->toArray();
        $title = trim((string) ($payload['title'] ?? ''));
        $scheduledAtRaw = (string) ($payload['scheduledAt'] ?? '');

        if ('' === $title || '' === $scheduledAtRaw) {
            return $this->json(['ok' => false, 'error' => 'invalid_payload'], 400);
        }

        try {
            $scheduledAt = new \DateTimeImmutable($scheduledAtRaw);
        } catch (\Exception) {
            return $this->json(['ok' => false, 'error' => 'invalid_scheduled_at'], 400);
        }

        $organizer = $this->workspaceAccess->getUserOrFail();

        $meeting = (new Meeting())
            ->setTeam($team)
            ->setOrganizer($organizer)
            ->setTitle($title)
            ->setScheduledAt($scheduledAt)
            ->setIsRecurring((bool) ($payload['isRecurring'] ?? false))
            ->setRecurrence($this->meetingWorkflow->buildRecurrenceLabel(
                (bool) ($payload['isRecurring'] ?? false),
                (string) ($payload['recurrenceQuantity'] ?? ''),
                (string) ($payload['recurrenceUnit'] ?? '')
            ))
            ->setVideoLink((string) ($payload['videoLink'] ?? '') ?: null);

        $this->entityManager->persist($meeting);

        foreach ($team->getMemberships() as $membership) {
            if ($membership->getUser()->getId() === $organizer->getId()) {
                continue;
            }

            $this->entityManager->persist(
                (new MeetingAttendee())
                    ->setMeeting($meeting)
                    ->setUser($membership->getUser())
            );
        }

        $this->entityManager->flush();

        return $this->json(['ok' => true, 'meetingId' => $meeting->getId()]);
    }
}
