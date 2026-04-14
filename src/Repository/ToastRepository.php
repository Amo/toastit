<?php

namespace App\Repository;

use App\Entity\Toast;
use App\Entity\User;
use App\Entity\Workspace;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Toast>
 */
class ToastRepository extends ServiceEntityRepository
{
    public const PUBLIC_STATUS_ALL = 'all';
    public const PUBLIC_STATUS_NEW = 'new';
    public const PUBLIC_STATUS_READY = 'ready';
    public const PUBLIC_STATUS_TOASTED = 'toasted';
    public const PUBLIC_STATUS_DISCARDED = 'discarded';

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Toast::class);
    }

    /**
     * @return list<Toast>
     */
    public function findAssignedActiveForUser(User $user, int $limit = 100): array
    {
        return $this->createQueryBuilder('toast')
            ->distinct()
            ->leftJoin('toast.workspace', 'workspace')
            ->addSelect('workspace')
            ->leftJoin('toast.votes', 'vote')
            ->addSelect('vote')
            ->where('toast.owner = :user')
            ->andWhere('toast.status = :pendingStatus')
            ->andWhere('workspace.deletedAt IS NULL')
            ->setParameter('user', $user)
            ->setParameter('pendingStatus', Toast::STATUS_PENDING)
            ->orderBy('toast.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function isSupportedPublicStatus(string $status): bool
    {
        return \in_array($status, [
            self::PUBLIC_STATUS_ALL,
            self::PUBLIC_STATUS_NEW,
            self::PUBLIC_STATUS_READY,
            self::PUBLIC_STATUS_TOASTED,
            self::PUBLIC_STATUS_DISCARDED,
        ], true);
    }

    /**
     * @return list<Toast>
     */
    public function findCreatedByUserSince(User $user, \DateTimeImmutable $since): array
    {
        return $this->createRecentUserScopeQueryBuilder($user, $since)
            ->andWhere('toast.author = :user')
            ->andWhere('toast.createdAt >= :since')
            ->orderBy('toast.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return list<Toast>
     */
    public function findCreatedByUserAndCompletedSince(User $user, \DateTimeImmutable $since): array
    {
        return $this->createRecentUserScopeQueryBuilder($user, $since)
            ->andWhere('toast.author = :user')
            ->andWhere('toast.status = :toastedStatus')
            ->andWhere('toast.statusChangedAt >= :since')
            ->setParameter('toastedStatus', Toast::STATUS_TOASTED)
            ->orderBy('toast.statusChangedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return list<Toast>
     */
    public function findAssignedToUserAndCompletedSince(User $user, \DateTimeImmutable $since): array
    {
        return $this->createRecentUserScopeQueryBuilder($user, $since)
            ->andWhere('toast.owner = :user')
            ->andWhere('toast.status = :toastedStatus')
            ->andWhere('toast.statusChangedAt >= :since')
            ->setParameter('toastedStatus', Toast::STATUS_TOASTED)
            ->orderBy('toast.statusChangedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return list<int>
     */
    public function findInvolvedToastIdsForUser(User $user, int $limit = 500): array
    {
        $rows = $this->createQueryBuilder('toast')
            ->select('DISTINCT toast.id AS id')
            ->leftJoin('toast.workspace', 'workspace')
            ->leftJoin('toast.comments', 'comment')
            ->where('workspace.deletedAt IS NULL')
            ->andWhere('toast.author = :user OR toast.owner = :user OR comment.author = :user')
            ->setParameter('user', $user)
            ->orderBy('toast.id', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getArrayResult();

        return array_values(array_filter(array_map(
            static fn (array $row): int => (int) ($row['id'] ?? 0),
            $rows,
        )));
    }

    /**
     * @param list<int> $toastIds
     *
     * @return list<Toast>
     */
    public function findStatusChangedForToastIdsBetween(array $toastIds, \DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        if ([] === $toastIds) {
            return [];
        }

        return $this->createQueryBuilder('toast')
            ->leftJoin('toast.workspace', 'workspace')
            ->addSelect('workspace')
            ->leftJoin('toast.author', 'author')
            ->addSelect('author')
            ->leftJoin('toast.owner', 'owner')
            ->addSelect('owner')
            ->where('toast.id IN (:ids)')
            ->andWhere('toast.statusChangedAt IS NOT NULL')
            ->andWhere('toast.statusChangedAt >= :from')
            ->andWhere('toast.statusChangedAt <= :to')
            ->setParameter('ids', $toastIds)
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->orderBy('toast.statusChangedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return array{toasts: list<Toast>, total: int}
     */
    public function findPaginatedForWorkspace(Workspace $workspace, string $status, int $page, int $perPage): array
    {
        $status = strtolower(trim($status));
        $offset = max(0, ($page - 1) * $perPage);

        $itemsQb = $this->createQueryBuilder('toast')
            ->leftJoin('toast.author', 'author')
            ->addSelect('author')
            ->leftJoin('toast.owner', 'owner')
            ->addSelect('owner')
            ->where('toast.workspace = :workspace')
            ->setParameter('workspace', $workspace)
            ->orderBy('toast.createdAt', 'DESC')
            ->setFirstResult($offset)
            ->setMaxResults($perPage);

        $countQb = $this->createQueryBuilder('toast')
            ->select('COUNT(toast.id)')
            ->where('toast.workspace = :workspace')
            ->setParameter('workspace', $workspace);

        $this->applyPublicStatusFilter($itemsQb, $status);
        $this->applyPublicStatusFilter($countQb, $status);

        return [
            'toasts' => $itemsQb->getQuery()->getResult(),
            'total' => (int) $countQb->getQuery()->getSingleScalarResult(),
        ];
    }

    private function applyPublicStatusFilter(QueryBuilder $qb, string $status): void
    {
        if (self::PUBLIC_STATUS_ALL === $status) {
            return;
        }

        if (self::PUBLIC_STATUS_NEW === $status) {
            $qb
                ->andWhere('toast.status = :statusPending')
                ->setParameter('statusPending', Toast::STATUS_PENDING);

            return;
        }

        if (self::PUBLIC_STATUS_READY === $status) {
            $qb
                ->andWhere('toast.status = :statusReady')
                ->setParameter('statusReady', Toast::STATUS_READY);

            return;
        }

        if (self::PUBLIC_STATUS_TOASTED === $status) {
            $qb
                ->andWhere('toast.status = :statusTreated')
                ->setParameter('statusTreated', Toast::STATUS_TOASTED);

            return;
        }

        if (self::PUBLIC_STATUS_DISCARDED === $status) {
            $qb
                ->andWhere('toast.status = :statusVetoed')
                ->setParameter('statusVetoed', Toast::STATUS_DISCARDED);
        }
    }

    private function createRecentUserScopeQueryBuilder(User $user, \DateTimeImmutable $since): QueryBuilder
    {
        return $this->createQueryBuilder('toast')
            ->distinct()
            ->leftJoin('toast.workspace', 'workspace')
            ->addSelect('workspace')
            ->leftJoin('toast.author', 'author')
            ->addSelect('author')
            ->leftJoin('toast.owner', 'owner')
            ->addSelect('owner')
            ->where('workspace.deletedAt IS NULL')
            ->setParameter('user', $user)
            ->setParameter('since', $since);
    }
}
