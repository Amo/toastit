<?php

namespace App\Repository;

use App\Entity\ToastReplyToken;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ToastReplyToken>
 */
class ToastReplyTokenRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ToastReplyToken::class);
    }

    public function invalidateActiveTokens(User $user, int $toastId, string $action, \DateTimeImmutable $now): void
    {
        $this->createQueryBuilder('token')
            ->update()
            ->set('token.usedAt', ':usedAt')
            ->where('token.user = :user')
            ->andWhere('token.toast = :toastId')
            ->andWhere('token.action = :action')
            ->andWhere('token.usedAt IS NULL')
            ->andWhere('token.expiresAt > :now')
            ->setParameter('usedAt', $now)
            ->setParameter('user', $user)
            ->setParameter('toastId', $toastId)
            ->setParameter('action', $action)
            ->setParameter('now', $now)
            ->getQuery()
            ->execute();
    }

    public function findActiveBySelector(string $selector, \DateTimeImmutable $now): ?ToastReplyToken
    {
        return $this->createQueryBuilder('token')
            ->andWhere('token.selector = :selector')
            ->andWhere('token.usedAt IS NULL')
            ->andWhere('token.expiresAt > :now')
            ->setParameter('selector', $selector)
            ->setParameter('now', $now)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
