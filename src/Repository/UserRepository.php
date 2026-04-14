<?php

namespace App\Repository;

use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<User>
 */
class UserRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, User::class);
    }

    public function findOneByNormalizedEmail(string $email): ?User
    {
        return $this->findOneBy(['email' => $email]);
    }

    public function findOneByInboundEmailAlias(string $inboundEmailAlias): ?User
    {
        return $this->findOneBy(['inboundEmailAlias' => mb_strtolower(trim($inboundEmailAlias))]);
    }

    /**
     * @return list<User>
     */
    public function findDigestRecipients(): array
    {
        return $this->createQueryBuilder('user')
            ->where('user.pinHash IS NOT NULL')
            ->orderBy('user.id', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function isEligibleForDailyDigest(User $user): bool
    {
        return null !== $user->getPublicEmail() && $user->hasPin();
    }
}
