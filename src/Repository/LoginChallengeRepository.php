<?php

namespace App\Repository;

use App\Entity\LoginChallenge;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<LoginChallenge>
 */
class LoginChallengeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, LoginChallenge::class);
    }

    public function invalidateActiveChallenges(User $user, string $purpose, \DateTimeImmutable $now): void
    {
        $this->createQueryBuilder('challenge')
            ->update()
            ->set('challenge.usedAt', ':usedAt')
            ->where('challenge.user = :user')
            ->andWhere('challenge.purpose = :purpose')
            ->andWhere('challenge.usedAt IS NULL')
            ->andWhere('challenge.expiresAt > :now')
            ->setParameter('usedAt', $now)
            ->setParameter('user', $user)
            ->setParameter('purpose', $purpose)
            ->setParameter('now', $now)
            ->getQuery()
            ->execute();
    }

    public function findLatestActiveCodeChallenge(User $user, string $purpose, string $code, \DateTimeImmutable $now): ?LoginChallenge
    {
        return $this->createQueryBuilder('challenge')
            ->andWhere('challenge.user = :user')
            ->andWhere('challenge.purpose = :purpose')
            ->andWhere('challenge.code = :code')
            ->andWhere('challenge.usedAt IS NULL')
            ->andWhere('challenge.expiresAt > :now')
            ->setParameter('user', $user)
            ->setParameter('purpose', $purpose)
            ->setParameter('code', $code)
            ->setParameter('now', $now)
            ->orderBy('challenge.id', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findActiveBySelector(string $selector, \DateTimeImmutable $now): ?LoginChallenge
    {
        return $this->createQueryBuilder('challenge')
            ->andWhere('challenge.selector = :selector')
            ->andWhere('challenge.usedAt IS NULL')
            ->andWhere('challenge.expiresAt > :now')
            ->setParameter('selector', $selector)
            ->setParameter('now', $now)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
