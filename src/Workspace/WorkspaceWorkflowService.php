<?php

namespace App\Workspace;

use App\Entity\Toast;
use App\Entity\User;
use App\Entity\Workspace;

final class WorkspaceWorkflowService
{
    /**
     * @return list<User>
     */
    public function getWorkspaceInvitees(Workspace $workspace): array
    {
        $invitees = [$workspace->getOrganizer()];

        foreach ($workspace->getMemberships() as $membership) {
            $invitees[] = $membership->getUser();
        }

        $inviteesById = [];

        foreach ($invitees as $invitee) {
            $inviteesById[$invitee->getId() ?? spl_object_id($invitee)] = $invitee;
        }

        $invitees = array_values($inviteesById);

        usort($invitees, static fn (User $left, User $right): int => strcmp($left->getDisplayName(), $right->getDisplayName()));

        return $invitees;
    }

    public function findWorkspaceInviteeById(Workspace $workspace, int $userId): ?User
    {
        if ($userId <= 0) {
            return null;
        }

        foreach ($this->getWorkspaceInvitees($workspace) as $invitee) {
            if ($invitee->getId() === $userId) {
                return $invitee;
            }
        }

        return null;
    }

    public function findWorkspaceInviteeByDisplayName(Workspace $workspace, string $displayName): ?User
    {
        $displayName = trim($displayName);
        if ('' === $displayName) {
            return null;
        }

        foreach ($this->getWorkspaceInvitees($workspace) as $invitee) {
            if (0 === strcasecmp($invitee->getDisplayName(), $displayName)) {
                return $invitee;
            }
        }

        return null;
    }

    public function findWorkspaceInviteeByEmail(Workspace $workspace, string $email): ?User
    {
        $email = strtolower(trim($email));
        if ('' === $email) {
            return null;
        }

        foreach ($this->getWorkspaceInvitees($workspace) as $invitee) {
            if (strtolower($invitee->getEmail()) === $email) {
                return $invitee;
            }
        }

        return null;
    }

    /**
     * @return array<int, string>
     */
    public function getWorkspaceInviteeNamesById(Workspace $workspace): array
    {
        $names = [];

        foreach ($this->getWorkspaceInvitees($workspace) as $invitee) {
            if (null !== $invitee->getId()) {
                $names[$invitee->getId()] = $invitee->getDisplayName();
            }
        }

        return $names;
    }

    public function nextBoostRank(Workspace $workspace): int
    {
        $maxRank = 0;

        foreach ($workspace->getItems() as $item) {
            if (!$item->isBoosted()) {
                continue;
            }

            $maxRank = max($maxRank, $item->getBoostRank() ?? 0);
        }

        return $maxRank + 1;
    }

    public function hasFollowUp(Toast $item, string $title, ?User $owner, ?\DateTimeImmutable $dueAt): bool
    {
        foreach ($item->getFollowUpChildren() as $child) {
            if ($child->getTitle() !== $title) {
                continue;
            }

            if (($child->getOwner()?->getId()) !== ($owner?->getId())) {
                continue;
            }

            if (($child->getDueAt()?->format('Y-m-d')) !== ($dueAt?->format('Y-m-d'))) {
                continue;
            }

            return true;
        }

        return false;
    }
}
