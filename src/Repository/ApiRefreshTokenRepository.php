<?php

namespace App\Repository;

use App\Entity\ApiRefreshToken;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ApiRefreshToken>
 */
class ApiRefreshTokenRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ApiRefreshToken::class);
    }

    public function findActiveByHash(string $tokenHash, \DateTimeImmutable $now): ?ApiRefreshToken
    {
        return $this->createQueryBuilder('refreshToken')
            ->andWhere('refreshToken.tokenHash = :tokenHash')
            ->andWhere('refreshToken.expiresAt > :now')
            ->setParameter('tokenHash', $tokenHash)
            ->setParameter('now', $now)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
