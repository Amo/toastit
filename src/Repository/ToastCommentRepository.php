<?php

namespace App\Repository;

use App\Entity\Toast;
use App\Entity\ToastComment;
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
}
