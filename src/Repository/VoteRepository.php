<?php

namespace App\Repository;

use App\Entity\ParkingLotItem;
use App\Entity\User;
use App\Entity\Vote;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Vote>
 */
class VoteRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Vote::class);
    }

    public function findOneForItemAndUser(ParkingLotItem $item, User $user): ?Vote
    {
        return $this->findOneBy([
            'item' => $item,
            'user' => $user,
        ]);
    }
}
