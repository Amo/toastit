<?php

namespace App\Repository;

use App\Entity\Toast;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Toast>
 */
class ToastRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Toast::class);
    }

    /**
     * @return list<Toast>
     */
    public function findAssignedActiveForUser(User $user, int $limit = 100): array
    {
        return $this->createQueryBuilder('toast')
            ->distinct()
            ->leftJoin('toast.workspace', 'workspace')
            ->addSelect('workspace')
            ->leftJoin('toast.votes', 'vote')
            ->addSelect('vote')
            ->where('toast.owner = :user')
            ->andWhere('toast.status = :openStatus')
            ->andWhere('toast.discussionStatus = :pendingStatus')
            ->andWhere('workspace.deletedAt IS NULL')
            ->setParameter('user', $user)
            ->setParameter('openStatus', Toast::STATUS_OPEN)
            ->setParameter('pendingStatus', Toast::DISCUSSION_PENDING)
            ->orderBy('toast.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}
