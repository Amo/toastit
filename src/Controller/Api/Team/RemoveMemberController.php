<?php

namespace App\Controller\Api\Team;

use App\Entity\TeamMember;
use App\Workspace\WorkspaceAccess;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

final class RemoveMemberController extends AbstractController
{
    public function __construct(
        private readonly WorkspaceAccess $workspaceAccess,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    #[Route('/api/teams/{teamId}/members/{memberId}', name: 'api_team_member_remove', methods: ['DELETE'])]
    public function __invoke(int $teamId, int $memberId): JsonResponse
    {
        $team = $this->workspaceAccess->getTeamOrFail($teamId);
        $membership = $this->entityManager->getRepository(TeamMember::class)->find($memberId);

        if (!$membership instanceof TeamMember || $membership->getTeam()->getId() !== $team->getId()) {
            return $this->json(['ok' => false, 'error' => 'member_not_found'], 404);
        }

        $user = $membership->getUser();

        if ($team->getOrganizer()->getId() === $user->getId()) {
            return $this->json(['ok' => false, 'error' => 'cannot_remove_organizer'], 400);
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

        return $this->json(['ok' => true]);
    }
}
