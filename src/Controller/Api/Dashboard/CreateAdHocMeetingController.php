<?php

namespace App\Controller\Api\Dashboard;

use App\Entity\Meeting;
use App\Workspace\MeetingWorkflow;
use App\Workspace\WorkspaceAccess;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

final class CreateAdHocMeetingController extends AbstractController
{
    public function __construct(
        private readonly WorkspaceAccess $workspaceAccess,
        private readonly MeetingWorkflow $meetingWorkflow,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    #[Route('/api/meetings/ad-hoc', name: 'api_ad_hoc_meeting_create', methods: ['POST'])]
    public function __invoke(Request $request): JsonResponse
    {
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

        $meeting = (new Meeting())
            ->setOrganizer($this->workspaceAccess->getUserOrFail())
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
        $this->entityManager->flush();

        return $this->json(['ok' => true, 'meetingId' => $meeting->getId()]);
    }
}
