<?php

namespace App\Repository;

use App\Entity\User;
use App\Entity\Workspace;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Workspace>
 */
class WorkspaceRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Workspace::class);
    }

    /** @return list<Workspace> */
    public function findForUser(User $user): array
    {
        return $this->createQueryBuilder('workspace')
            ->leftJoin('workspace.memberships', 'membership')
            ->addSelect('membership')
            ->leftJoin('membership.user', 'memberUser')
            ->addSelect('memberUser')
            ->leftJoin('workspace.items', 'item')
            ->addSelect('item')
            ->where('membership.user = :user OR workspace.organizer = :user')
            ->setParameter('user', $user)
            ->orderBy('workspace.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findOneForUser(int $workspaceId, User $user): ?Workspace
    {
        return $this->createQueryBuilder('workspace')
            ->leftJoin('workspace.memberships', 'membership')
            ->addSelect('membership')
            ->leftJoin('membership.user', 'memberUser')
            ->addSelect('memberUser')
            ->leftJoin('workspace.items', 'item')
            ->addSelect('item')
            ->leftJoin('item.votes', 'vote')
            ->addSelect('vote')
            ->leftJoin('item.followUpChildren', 'followUpChild')
            ->addSelect('followUpChild')
            ->where('workspace.id = :workspaceId')
            ->andWhere('membership.user = :user OR workspace.organizer = :user')
            ->setParameter('workspaceId', $workspaceId)
            ->setParameter('user', $user)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
