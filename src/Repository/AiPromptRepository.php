<?php

namespace App\Repository;

use App\Entity\AiPrompt;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<AiPrompt>
 */
final class AiPromptRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AiPrompt::class);
    }

    /** @return list<AiPrompt> */
    public function findAllOrderedByLabel(): array
    {
        return $this->createQueryBuilder('prompt')
            ->orderBy('prompt.label', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findOneByCode(string $code): ?AiPrompt
    {
        return $this->findOneBy(['code' => trim($code)]);
    }
}

