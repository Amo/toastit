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
            ->leftJoin('workspace.memberships', 'dashboardMembership', 'WITH', 'dashboardMembership.user = :user')
            ->addSelect('dashboardMembership')
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
            ->andWhere('workspace.deletedAt IS NULL')
            ->andWhere('workspace.isInboxWorkspace = false')
            ->setParameter('user', $user)
            ->orderBy('dashboardMembership.displayOrder', 'ASC')
            ->addOrderBy('workspace.createdAt', 'DESC')
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
            ->andWhere('workspace.deletedAt IS NULL')
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

    /**
     * @return list<Workspace>
     */
    public function findDeletedOwnedByUser(User $user): array
    {
        return $this->createQueryBuilder('workspace')
            ->distinct()
            ->leftJoin('workspace.memberships', 'membership')
            ->addSelect('membership')
            ->leftJoin('membership.user', 'memberUser')
            ->addSelect('memberUser')
            ->where('workspace.deletedAt IS NOT NULL')
            ->andWhere('workspace.isInboxWorkspace = false')
            ->andWhere('(
                workspace.organizer = :user
                OR EXISTS (
                    SELECT membership_match.id
                    FROM App\Entity\WorkspaceMember membership_match
                    WHERE membership_match.workspace = workspace
                    AND membership_match.user = :user
                    AND membership_match.isOwner = true
                )
            )')
            ->setParameter('user', $user)
            ->orderBy('workspace.deletedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findOneDeletedOwnedByUser(int $workspaceId, User $user): ?Workspace
    {
        return $this->createQueryBuilder('workspace')
            ->where('workspace.id = :workspaceId')
            ->andWhere('workspace.deletedAt IS NOT NULL')
            ->andWhere('(
                workspace.organizer = :user
                OR EXISTS (
                    SELECT membership_match.id
                    FROM App\Entity\WorkspaceMember membership_match
                    WHERE membership_match.workspace = workspace
                    AND membership_match.user = :user
                    AND membership_match.isOwner = true
                )
            )')
            ->setParameter('workspaceId', $workspaceId)
            ->setParameter('user', $user)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findInboxWorkspaceForUser(User $user): ?Workspace
    {
        return $this->createQueryBuilder('workspace')
            ->where('workspace.organizer = :user')
            ->andWhere('workspace.deletedAt IS NULL')
            ->andWhere('workspace.isInboxWorkspace = true')
            ->setParameter('user', $user)
            ->orderBy('workspace.id', 'ASC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findDefaultWorkspaceForUser(User $user): ?Workspace
    {
        return $this->createQueryBuilder('workspace')
            ->distinct()
            ->leftJoin('workspace.memberships', 'membership')
            ->where('workspace.deletedAt IS NULL')
            ->andWhere('workspace.isInboxWorkspace = false')
            ->andWhere('workspace.isDefault = true')
            ->andWhere('(
                workspace.organizer = :user
                OR membership.user = :user
            )')
            ->setParameter('user', $user)
            ->orderBy('workspace.id', 'ASC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
