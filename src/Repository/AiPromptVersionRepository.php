<?php

namespace App\Repository;

use App\Entity\AiPrompt;
use App\Entity\AiPromptVersion;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<AiPromptVersion>
 */
final class AiPromptVersionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AiPromptVersion::class);
    }

    public function findLatestForPrompt(AiPrompt $prompt): ?AiPromptVersion
    {
        return $this->createQueryBuilder('version')
            ->where('version.prompt = :prompt')
            ->setParameter('prompt', $prompt)
            ->orderBy('version.versionNumber', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /** @return list<AiPromptVersion> */
    public function findLatestVersions(int $limit = 20): array
    {
        return $this->createQueryBuilder('version')
            ->leftJoin('version.prompt', 'prompt')
            ->addSelect('prompt')
            ->leftJoin('version.changedByUser', 'changedByUser')
            ->addSelect('changedByUser')
            ->orderBy('version.changedAt', 'DESC')
            ->setMaxResults(max(1, $limit))
            ->getQuery()
            ->getResult();
    }

    /** @return list<AiPromptVersion> */
    public function findAllForPrompt(AiPrompt $prompt): array
    {
        return $this->createQueryBuilder('version')
            ->leftJoin('version.changedByUser', 'changedByUser')
            ->addSelect('changedByUser')
            ->where('version.prompt = :prompt')
            ->setParameter('prompt', $prompt)
            ->orderBy('version.versionNumber', 'DESC')
            ->getQuery()
            ->getResult();
    }
}

