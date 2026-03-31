<?php

namespace App\Repository;

use App\Entity\Meeting;
use App\Entity\MeetingAttendee;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<MeetingAttendee>
 */
class MeetingAttendeeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, MeetingAttendee::class);
    }

    public function findOneForMeetingAndUser(Meeting $meeting, User $user): ?MeetingAttendee
    {
        return $this->findOneBy([
            'meeting' => $meeting,
            'user' => $user,
        ]);
    }
}
