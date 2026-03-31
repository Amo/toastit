<?php

namespace App\Controller\App\Meeting;

use App\Entity\MeetingAttendee;
use App\Workspace\UserProvisioner;
use App\Workspace\WorkspaceAccess;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

final class InviteController extends AbstractController
{
    public function __construct(
        private readonly WorkspaceAccess $workspaceAccess,
        private readonly UserProvisioner $userProvisioner,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    #[Route('/app/meetings/{id}/invite', name: 'app_meeting_invite', methods: ['POST'])]
    public function __invoke(int $id, Request $request): RedirectResponse
    {
        $meeting = $this->workspaceAccess->getMeetingOrFail($id);
        $email = trim($request->request->getString('email'));

        if ('' === $email || false === filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->addFlash('error', 'Merci de renseigner une adresse email valide.');

            return $this->redirectToRoute('app_meeting_show', ['id' => $meeting->getId()]);
        }

        $user = $this->userProvisioner->findOrCreateUserByEmail($email);

        if ($meeting->getOrganizer()->getId() === $user->getId()) {
            $this->addFlash('success', sprintf('%s est deja organisateur de ce meeting.', $user->getDisplayName()));

            return $this->redirectToRoute('app_meeting_show', ['id' => $meeting->getId()]);
        }

        foreach ($meeting->getAttendees() as $attendee) {
            if ($attendee->getUser()->getId() === $user->getId()) {
                $this->addFlash('success', sprintf('%s est deja invite a ce meeting.', $user->getDisplayName()));

                return $this->redirectToRoute('app_meeting_show', ['id' => $meeting->getId()]);
            }
        }

        $attendee = (new MeetingAttendee())
            ->setMeeting($meeting)
            ->setUser($user);

        $this->entityManager->persist($attendee);
        $this->entityManager->flush();
        $this->addFlash('success', sprintf('%s a ete invite au meeting.', $user->getDisplayName()));

        return $this->redirectToRoute('app_meeting_show', ['id' => $meeting->getId()]);
    }
}
