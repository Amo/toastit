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
}
