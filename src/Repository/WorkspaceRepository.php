<?php

namespace App\Repository;

use App\Entity\User;
use App\Entity\Workspace;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Workspace>
 */
class WorkspaceRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Workspace::class);
    }

    /** @return list<Workspace> */
    public function findForUser(User $user): array
    {
        return $this->createQueryBuilder('workspace')
            ->distinct()
            ->leftJoin('workspace.memberships', 'membership')
            ->addSelect('membership')
            ->leftJoin('membership.user', 'memberUser')
            ->addSelect('memberUser')
            ->leftJoin('workspace.items', 'item')
            ->addSelect('item')
            ->where('workspace.organizer = :user')
            ->orWhere('EXISTS (
                SELECT membership_match.id
                FROM App\Entity\WorkspaceMember membership_match
                WHERE membership_match.workspace = workspace
                AND membership_match.user = :user
            )')
            ->setParameter('user', $user)
            ->orderBy('workspace.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findOneForUser(int $workspaceId, User $user): ?Workspace
    {
        return $this->createQueryBuilder('workspace')
            ->distinct()
            ->leftJoin('workspace.memberships', 'membership')
            ->addSelect('membership')
            ->leftJoin('membership.user', 'memberUser')
            ->addSelect('memberUser')
            ->leftJoin('workspace.items', 'item')
            ->addSelect('item')
            ->leftJoin('item.votes', 'vote')
            ->addSelect('vote')
            ->leftJoin('item.comments', 'comment')
            ->addSelect('comment')
            ->leftJoin('comment.author', 'commentAuthor')
            ->addSelect('commentAuthor')
            ->leftJoin('item.previousItem', 'previousItem')
            ->addSelect('previousItem')
            ->leftJoin('item.followUpChildren', 'followUpChild')
            ->addSelect('followUpChild')
            ->where('workspace.id = :workspaceId')
            ->andWhere('(
                workspace.organizer = :user
                OR EXISTS (
                    SELECT membership_match.id
                    FROM App\Entity\WorkspaceMember membership_match
                    WHERE membership_match.workspace = workspace
                    AND membership_match.user = :user
                )
            )')
            ->setParameter('workspaceId', $workspaceId)
            ->setParameter('user', $user)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
