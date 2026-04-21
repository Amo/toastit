<?php

namespace App\Repository;

use App\Entity\Workspace;
use App\Entity\WorkspaceNote;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<WorkspaceNote>
 */
final class WorkspaceNoteRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, WorkspaceNote::class);
    }

    /** @return list<WorkspaceNote> */
    public function findForWorkspace(Workspace $workspace): array
    {
        return $this->createQueryBuilder('note')
            ->distinct()
            ->leftJoin('note.author', 'author')
            ->addSelect('author')
            ->leftJoin('note.versions', 'version')
            ->addSelect('version')
            ->leftJoin('version.author', 'versionAuthor')
            ->addSelect('versionAuthor')
            ->where('note.workspace = :workspace')
            ->setParameter('workspace', $workspace)
            ->orderBy('note.isImportant', 'DESC')
            ->addOrderBy('note.updatedAt', 'DESC')
            ->addOrderBy('note.id', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
