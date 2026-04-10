<?php

namespace App\Repository;

use App\Entity\PersonalAccessToken;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PersonalAccessToken>
 */
class PersonalAccessTokenRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PersonalAccessToken::class);
    }

    public function findOneOwnedByUser(User $user, int $tokenId): ?PersonalAccessToken
    {
        return $this->createQueryBuilder('token')
            ->andWhere('token.id = :tokenId')
            ->andWhere('token.user = :user')
            ->setParameter('tokenId', $tokenId)
            ->setParameter('user', $user)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * @return list<PersonalAccessToken>
     */
    public function findVisibleOwnedByUser(User $user): array
    {
        return $this->createQueryBuilder('token')
            ->andWhere('token.user = :user')
            ->orderBy('token.createdAt', 'DESC')
            ->setParameter('user', $user)
            ->getQuery()
            ->getResult();
    }

    /**
     * @return list<PersonalAccessToken>
     */
    public function findActiveBySelector(string $selector, \DateTimeImmutable $now): array
    {
        return $this->createQueryBuilder('token')
            ->andWhere('token.selector = :selector')
            ->andWhere('token.revokedAt IS NULL')
            ->andWhere('token.expiresAt IS NULL OR token.expiresAt > :now')
            ->setParameter('selector', $selector)
            ->setParameter('now', $now)
            ->getQuery()
            ->getResult();
    }
}

