<?php

namespace App\Repository;

use App\Entity\WorkspaceNote;
use App\Entity\WorkspaceNoteVersion;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<WorkspaceNoteVersion>
 */
final class WorkspaceNoteVersionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, WorkspaceNoteVersion::class);
    }

    /** @return list<WorkspaceNoteVersion> */
    public function findHistoryForNote(WorkspaceNote $note): array
    {
        return $this->createQueryBuilder('version')
            ->leftJoin('version.author', 'author')
            ->addSelect('author')
            ->where('version.note = :note')
            ->setParameter('note', $note)
            ->orderBy('version.recordedAt', 'DESC')
            ->addOrderBy('version.id', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
