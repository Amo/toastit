<?php

namespace App\Controller\Api\Team;

use App\Entity\Meeting;
use App\Entity\MeetingAttendee;
use App\Entity\TeamMember;
use App\Workspace\UserProvisioner;
use App\Workspace\WorkspaceAccess;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
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

    #[Route('/api/teams/{id}/invite', name: 'api_team_invite', methods: ['POST'])]
    public function __invoke(int $id, Request $request): JsonResponse
    {
        $team = $this->workspaceAccess->getTeamOrFail($id);
        $payload = $request->toArray();
        $email = trim((string) ($payload['email'] ?? ''));

        if ('' === $email || false === filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $this->json(['ok' => false, 'error' => 'invalid_email'], 400);
        }

        $user = $this->userProvisioner->findOrCreateUserByEmail($email);

        foreach ($team->getMemberships() as $membership) {
            if ($membership->getUser()->getId() === $user->getId()) {
                return $this->json(['ok' => true, 'alreadyMember' => true]);
            }
        }

        $membership = (new TeamMember())
            ->setTeam($team)
            ->setUser($user);

        $this->entityManager->persist($membership);

        foreach ($team->getMeetings() as $meeting) {
            if ($meeting->isClosed() || $meeting->getOrganizer()->getId() === $user->getId()) {
                continue;
            }

            $alreadyAttendee = false;
            foreach ($meeting->getAttendees() as $attendee) {
                if ($attendee->getUser()->getId() === $user->getId()) {
                    $alreadyAttendee = true;
                    break;
                }
            }

            if ($alreadyAttendee) {
                continue;
            }

            $this->entityManager->persist(
                (new MeetingAttendee())
                    ->setMeeting($meeting)
                    ->setUser($user)
            );
        }

        $this->entityManager->flush();

        return $this->json(['ok' => true]);
    }
}
