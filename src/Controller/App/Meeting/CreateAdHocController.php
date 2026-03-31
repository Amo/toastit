<?php

namespace App\Controller\App\Meeting;

use App\Entity\Meeting;
use App\Workspace\MeetingWorkflow;
use App\Workspace\WorkspaceAccess;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

final class CreateAdHocController extends AbstractController
{
    public function __construct(
        private readonly WorkspaceAccess $workspaceAccess,
        private readonly MeetingWorkflow $meetingWorkflow,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    #[Route('/app/meetings/ad-hoc', name: 'app_ad_hoc_meeting_create', methods: ['POST'])]
    public function __invoke(Request $request): RedirectResponse
    {
        $title = trim($request->request->getString('title'));
        $scheduledAtRaw = $request->request->getString('scheduled_at');

        if ('' === $title || '' === $scheduledAtRaw) {
            $this->addFlash('error', 'Le titre et la date du meeting sont requis.');

            return $this->redirectToRoute('app_dashboard');
        }

        try {
            $scheduledAt = new \DateTimeImmutable($scheduledAtRaw);
        } catch (\Exception) {
            $this->addFlash('error', 'La date du meeting est invalide.');

            return $this->redirectToRoute('app_dashboard');
        }

        $meeting = (new Meeting())
            ->setOrganizer($this->workspaceAccess->getUserOrFail())
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
        $this->entityManager->flush();

        return $this->redirectToRoute('app_meeting_show', ['id' => $meeting->getId()]);
    }
}
