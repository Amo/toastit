<?php

namespace App\Controller\App\Team;

use App\Entity\Meeting;
use App\Entity\MeetingAttendee;
use App\Entity\TeamMember;
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

    #[Route('/app/teams/{id}/invite', name: 'app_team_invite', methods: ['POST'])]
    public function __invoke(int $id, Request $request): RedirectResponse
    {
        $team = $this->workspaceAccess->getTeamOrFail($id);
        $email = trim($request->request->getString('email'));

        if ('' === $email || false === filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->addFlash('error', 'Merci de renseigner une adresse email valide.');

            return $this->redirectToRoute('app_team_show', ['id' => $team->getId()]);
        }

        $user = $this->userProvisioner->findOrCreateUserByEmail($email);

        foreach ($team->getMemberships() as $membership) {
            if ($membership->getUser()->getId() === $user->getId()) {
                $this->addFlash('success', sprintf('%s fait deja partie de l equipe.', $user->getDisplayName()));

                return $this->redirectToRoute('app_team_show', ['id' => $team->getId()]);
            }
        }

        $membership = (new TeamMember())
            ->setTeam($team)
            ->setUser($user);

        $this->entityManager->persist($membership);

        foreach ($team->getMeetings() as $meeting) {
            if ($meeting->isClosed()) {
                continue;
            }

            if ($meeting->getOrganizer()->getId() === $user->getId()) {
                continue;
            }

            if ($this->hasMeetingAttendee($meeting, $user)) {
                continue;
            }

            $attendee = (new MeetingAttendee())
                ->setMeeting($meeting)
                ->setUser($user);

            $this->entityManager->persist($attendee);
        }

        $this->entityManager->flush();
        $this->addFlash('success', sprintf('%s a ete ajoute a l equipe.', $user->getDisplayName()));

        return $this->redirectToRoute('app_team_show', ['id' => $team->getId()]);
    }

    private function hasMeetingAttendee(Meeting $meeting, \App\Entity\User $user): bool
    {
        foreach ($meeting->getAttendees() as $attendee) {
            if ($attendee->getUser()->getId() === $user->getId()) {
                return true;
            }
        }

        return false;
    }
}
