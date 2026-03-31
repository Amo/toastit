<?php

namespace App\Controller\App\Team;

use App\Entity\Meeting;
use App\Entity\MeetingAttendee;
use App\Workspace\MeetingWorkflow;
use App\Workspace\WorkspaceAccess;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
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

    #[Route('/app/teams/{id}/meetings', name: 'app_team_meeting_create', methods: ['POST'])]
    public function __invoke(int $id, Request $request): RedirectResponse
    {
        $team = $this->workspaceAccess->getTeamOrFail($id);
        $title = trim($request->request->getString('title'));
        $scheduledAtRaw = $request->request->getString('scheduled_at');

        if ('' === $title || '' === $scheduledAtRaw) {
            $this->addFlash('error', 'Le titre et la date du meeting sont requis.');

            return $this->redirectToRoute('app_team_show', ['id' => $team->getId()]);
        }

        try {
            $scheduledAt = new \DateTimeImmutable($scheduledAtRaw);
        } catch (\Exception) {
            $this->addFlash('error', 'La date du meeting est invalide.');

            return $this->redirectToRoute('app_team_show', ['id' => $team->getId()]);
        }

        $organizer = $this->workspaceAccess->getUserOrFail();

        $meeting = (new Meeting())
            ->setTeam($team)
            ->setOrganizer($organizer)
            ->setTitle($title)
            ->setScheduledAt($scheduledAt)
            ->setIsRecurring($request->request->getBoolean('is_recurring'))
            ->setRecurrence($this->meetingWorkflow->buildRecurrenceLabel(
                $request->request->getBoolean('is_recurring'),
                $request->request->getString('recurrence_quantity'),
                $request->request->getString('recurrence_unit')
            ))
            ->setVideoLink($request->request->getString('video_link') ?: null);

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

        return $this->redirectToRoute('app_team_show', ['id' => $team->getId()]);
    }
}
