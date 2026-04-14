<?php

namespace App\Repository;

use App\Entity\Toast;
use App\Entity\ToastComment;
use Doctrine\ORM\Query\Expr\Join;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ToastComment>
 */
class ToastCommentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ToastComment::class);
    }

    /**
     * @return array{comments: list<ToastComment>, total: int}
     */
    public function findPaginatedForToast(Toast $toast, int $page, int $perPage): array
    {
        $offset = max(0, ($page - 1) * $perPage);

        $comments = $this->createQueryBuilder('comment')
            ->leftJoin('comment.author', 'author')
            ->addSelect('author')
            ->where('comment.toast = :toast')
            ->setParameter('toast', $toast)
            ->orderBy('comment.createdAt', 'ASC')
            ->setFirstResult($offset)
            ->setMaxResults($perPage)
            ->getQuery()
            ->getResult();

        $total = (int) $this->createQueryBuilder('comment')
            ->select('COUNT(comment.id)')
            ->where('comment.toast = :toast')
            ->setParameter('toast', $toast)
            ->getQuery()
            ->getSingleScalarResult();

        return [
            'comments' => $comments,
            'total' => $total,
        ];
    }

    /**
     * @param list<int> $toastIds
     *
     * @return list<ToastComment>
     */
    public function findForToastIdsBetween(array $toastIds, \DateTimeImmutable $from, \DateTimeImmutable $to, int $limit = 300): array
    {
        if ([] === $toastIds) {
            return [];
        }

        return $this->createQueryBuilder('comment')
            ->leftJoin('comment.author', 'author')
            ->addSelect('author')
            ->leftJoin('comment.toast', 'toast')
            ->addSelect('toast')
            ->leftJoin('toast.workspace', 'workspace', Join::WITH, 'workspace.deletedAt IS NULL')
            ->addSelect('workspace')
            ->where('toast.id IN (:ids)')
            ->andWhere('comment.createdAt >= :from')
            ->andWhere('comment.createdAt <= :to')
            ->setParameter('ids', $toastIds)
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->orderBy('comment.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}
