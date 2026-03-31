<?php

namespace App\Controller\App\Team;

use App\Entity\MeetingAttendee;
use App\Entity\TeamMember;
use App\Workspace\WorkspaceAccess;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Routing\Attribute\Route;

final class RemoveMemberController extends AbstractController
{
    public function __construct(
        private readonly WorkspaceAccess $workspaceAccess,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    #[Route('/app/teams/{teamId}/members/{memberId}/remove', name: 'app_team_member_remove', methods: ['POST'])]
    public function __invoke(int $teamId, int $memberId): RedirectResponse
    {
        $team = $this->workspaceAccess->getTeamOrFail($teamId);
        $membership = $this->entityManager->getRepository(TeamMember::class)->find($memberId);

        if (!$membership instanceof TeamMember || $membership->getTeam()->getId() !== $team->getId()) {
            throw $this->createNotFoundException();
        }

        $user = $membership->getUser();

        if ($team->getOrganizer()->getId() === $user->getId()) {
            $this->addFlash('error', 'L organisateur ne peut pas etre retire de l equipe.');

            return $this->redirectToRoute('app_team_show', ['id' => $team->getId()]);
        }

        foreach ($team->getMeetings() as $meeting) {
            if ($meeting->isClosed()) {
                continue;
            }

            foreach ($meeting->getAttendees() as $attendee) {
                if ($attendee->getUser()->getId() === $user->getId()) {
                    $this->entityManager->remove($attendee);
                }
            }
        }

        $this->entityManager->remove($membership);
        $this->entityManager->flush();
        $this->addFlash('success', sprintf('%s a ete retire de l equipe.', $user->getDisplayName()));

        return $this->redirectToRoute('app_team_show', ['id' => $team->getId()]);
    }
}
