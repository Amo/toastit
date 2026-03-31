<?php

namespace App\Repository;

use App\Entity\Team;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Team>
 */
class TeamRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Team::class);
    }

    /** @return list<Team> */
    public function findForUser(User $user): array
    {
        return $this->createQueryBuilder('team')
            ->leftJoin('team.memberships', 'membership')
            ->addSelect('membership')
            ->leftJoin('team.meetings', 'meeting')
            ->addSelect('meeting')
            ->leftJoin('team.items', 'item')
            ->addSelect('item')
            ->where('membership.user = :user OR team.organizer = :user')
            ->setParameter('user', $user)
            ->orderBy('team.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findOneForUser(int $teamId, User $user): ?Team
    {
        return $this->createQueryBuilder('team')
            ->leftJoin('team.memberships', 'membership')
            ->addSelect('membership')
            ->leftJoin('team.meetings', 'meeting')
            ->addSelect('meeting')
            ->leftJoin('team.items', 'item')
            ->addSelect('item')
            ->leftJoin('item.votes', 'vote')
            ->addSelect('vote')
            ->where('team.id = :teamId')
            ->andWhere('membership.user = :user OR team.organizer = :user')
            ->setParameter('teamId', $teamId)
            ->setParameter('user', $user)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
