<?php

namespace App\Workspace;

use App\Entity\Meeting;
use App\Entity\MeetingAttendee;
use App\Entity\ParkingLotItem;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

final class MeetingWorkflow
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @return list<User>
     */
    public function getMeetingInvitees(Meeting $meeting): array
    {
        $invitees = [];

        foreach ($meeting->getAttendees() as $attendee) {
            $invitees[] = $attendee->getUser();
        }

        usort($invitees, static fn (User $left, User $right): int => strcmp($left->getDisplayName(), $right->getDisplayName()));

        return $invitees;
    }

    public function findMeetingInviteeById(Meeting $meeting, int $userId): ?User
    {
        if ($userId <= 0) {
            return null;
        }

        foreach ($this->getMeetingInvitees($meeting) as $invitee) {
            if ($invitee->getId() === $userId) {
                return $invitee;
            }
        }

        return null;
    }

    /**
     * @return array<int, string>
     */
    public function getMeetingInviteeNamesById(Meeting $meeting): array
    {
        $names = [];

        foreach ($this->getMeetingInvitees($meeting) as $invitee) {
            if (null !== $invitee->getId()) {
                $names[$invitee->getId()] = $invitee->getDisplayName();
            }
        }

        return $names;
    }

    public function buildRecurrenceLabel(bool $isRecurring, string $quantity, string $unit): ?string
    {
        if (!$isRecurring) {
            return null;
        }

        $quantity = trim($quantity);
        $unit = strtoupper(trim($unit));

        if (!ctype_digit($quantity)) {
            return null;
        }

        $quantityValue = (int) $quantity;

        if ($quantityValue < 1 || $quantityValue > 10) {
            return null;
        }

        $allowedUnits = ['D', 'W', 'M', 'Y'];

        if (!in_array($unit, $allowedUnits, true)) {
            return null;
        }

        return sprintf('P%d%s', $quantityValue, $unit);
    }

    public function nextBoostRank(Meeting $meeting): int
    {
        $maxRank = 0;

        foreach ($meeting->getParkingLotItems() as $meetingItem) {
            if (!$meetingItem->isBoosted()) {
                continue;
            }

            $maxRank = max($maxRank, $meetingItem->getBoostRank() ?? 0);
        }

        return $maxRank + 1;
    }

    public function createNextOccurrenceIfNeeded(Meeting $meeting): ?Meeting
    {
        $nextScheduledAt = $this->computeNextScheduledAt($meeting);

        if (!$nextScheduledAt instanceof \DateTimeImmutable) {
            return null;
        }

        $existingMeeting = $this->entityManager->getRepository(Meeting::class)->findOneBy([
            'team' => $meeting->getTeam(),
            'organizer' => $meeting->getOrganizer(),
            'title' => $meeting->getTitle(),
            'scheduledAt' => $nextScheduledAt,
        ]);

        if ($existingMeeting instanceof Meeting) {
            return $existingMeeting;
        }

        $nextMeeting = (new Meeting())
            ->setTeam($meeting->getTeam())
            ->setOrganizer($meeting->getOrganizer())
            ->setTitle($meeting->getTitle())
            ->setScheduledAt($nextScheduledAt)
            ->setIsRecurring(true)
            ->setRecurrence($meeting->getRecurrence())
            ->setVideoLink($meeting->getVideoLink());

        foreach ($meeting->getAttendees() as $attendee) {
            $nextMeeting->addAttendee(
                (new MeetingAttendee())
                    ->setUser($attendee->getUser())
            );
        }

        $this->entityManager->persist($nextMeeting);

        return $nextMeeting;
    }

    public function syncFollowUpsToNextOccurrence(Meeting $meeting, Meeting $nextMeeting): void
    {
        foreach ($meeting->getParkingLotItems() as $item) {
            foreach ($item->getFollowUpItems() as $followUpItem) {
                $followUpTitle = $followUpItem['title'];
                $description = sprintf('Suivi cree depuis "%s".', $item->getTitle());
                $existingItem = $this->entityManager->getRepository(ParkingLotItem::class)->findOneBy([
                    'meeting' => $nextMeeting,
                    'title' => $followUpTitle,
                    'description' => $description,
                    'author' => $item->getAuthor(),
                ]);

                if ($existingItem instanceof ParkingLotItem) {
                    continue;
                }

                $nextItem = (new ParkingLotItem())
                    ->setMeeting($nextMeeting)
                    ->setTeam($nextMeeting->getTeam())
                    ->setAuthor($item->getAuthor())
                    ->setTitle($followUpTitle)
                    ->setDescription($description)
                    ->setOwner($this->findMeetingInviteeById($meeting, (int) ($followUpItem['ownerId'] ?? 0)))
                    ->setDueAt($this->createDateOrNull($followUpItem['dueOn'] ?? null));

                $this->entityManager->persist($nextItem);
            }
        }
    }

    public function computeNextScheduledAt(Meeting $meeting): ?\DateTimeImmutable
    {
        $recurrence = trim((string) $meeting->getRecurrence());

        if ('' === $recurrence) {
            return null;
        }

        if (!preg_match('/^P([1-9]|10)[DWMY]$/', $recurrence)) {
            return null;
        }

        try {
            return $meeting->getScheduledAt()->add(new \DateInterval($recurrence));
        } catch (\Exception) {
            return null;
        }
    }

    private function createDateOrNull(?string $value): ?\DateTimeImmutable
    {
        if (null === $value || '' === trim($value)) {
            return null;
        }

        try {
            return new \DateTimeImmutable(trim($value));
        } catch (\Exception) {
            return null;
        }
    }
}
