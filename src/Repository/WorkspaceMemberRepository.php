<?php

namespace App\Repository;

use App\Entity\User;
use App\Entity\WorkspaceMember;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<WorkspaceMember>
 */
class WorkspaceMemberRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, WorkspaceMember::class);
    }

    public function nextDisplayOrderForUser(User $user): int
    {
        if (null === $user->getId()) {
            return 1;
        }

        $maxDisplayOrder = $this->createQueryBuilder('membership')
            ->select('MAX(membership.displayOrder)')
            ->where('membership.user = :user')
            ->setParameter('user', $user)
            ->getQuery()
            ->getSingleScalarResult();

        return (int) $maxDisplayOrder + 1;
    }
}
