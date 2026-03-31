<?php

namespace App\Workspace;

use App\Entity\Meeting;
use App\Entity\ParkingLotItem;
use App\Entity\Team;
use App\Entity\User;
use App\Repository\TeamRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class WorkspaceAccess
{
    public function __construct(
        private readonly TeamRepository $teamRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly Security $security,
    ) {
    }

    public function getUserOrFail(): User
    {
        $user = $this->security->getUser();

        if (!$user instanceof User) {
            throw new AccessDeniedHttpException();
        }

        return $user;
    }

    public function getTeamOrFail(int $teamId): Team
    {
        $team = $this->teamRepository->findOneForUser($teamId, $this->getUserOrFail());

        if (!$team instanceof Team) {
            throw new NotFoundHttpException();
        }

        return $team;
    }

    public function getMeetingOrFail(int $meetingId): Meeting
    {
        $meeting = $this->entityManager->getRepository(Meeting::class)->find($meetingId);

        if (!$meeting instanceof Meeting) {
            throw new NotFoundHttpException();
        }

        if (null !== $meeting->getTeam()) {
            $team = $this->getTeamOrFail($meeting->getTeam()->getId());

            if ($meeting->getTeam()->getId() !== $team->getId()) {
                throw new NotFoundHttpException();
            }

            return $meeting;
        }

        $user = $this->getUserOrFail();

        if ($meeting->getOrganizer()->getId() === $user->getId()) {
            return $meeting;
        }

        foreach ($meeting->getAttendees() as $attendee) {
            if ($attendee->getUser()->getId() === $user->getId()) {
                return $meeting;
            }
        }

        throw new NotFoundHttpException();
    }

    public function getItemOrFail(int $itemId): ParkingLotItem
    {
        $item = $this->entityManager->getRepository(ParkingLotItem::class)->find($itemId);

        if (!$item instanceof ParkingLotItem) {
            throw new NotFoundHttpException();
        }

        $this->getMeetingOrFail($item->getMeeting()->getId());

        return $item;
    }

    public function assertOrganizer(Meeting $meeting): void
    {
        if ($meeting->getOrganizer()->getId() !== $this->getUserOrFail()->getId()) {
            throw new AccessDeniedHttpException();
        }
    }

    public function assertMeetingEditable(Meeting $meeting): void
    {
        if ($meeting->isClosed()) {
            throw new AccessDeniedHttpException();
        }
    }
}
