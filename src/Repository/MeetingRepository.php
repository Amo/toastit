<?php

namespace App\Repository;

use App\Entity\Meeting;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Meeting>
 */
class MeetingRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Meeting::class);
    }

    /** @return list<Meeting> */
    public function findAdHocForUser(User $user): array
    {
        return $this->createQueryBuilder('meeting')
            ->leftJoin('meeting.attendees', 'attendee')
            ->addSelect('attendee')
            ->where('meeting.organizer = :user OR attendee.user = :user')
            ->andWhere('meeting.team IS NULL')
            ->setParameter('user', $user)
            ->orderBy('meeting.scheduledAt', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
