<?php

namespace App\Workspace;

use App\Entity\Toast;

final class AssignedToastPriorityService
{
    /**
     * @param list<Toast> $assignedToasts
     *
     * @return list<Toast>
     */
    public function sort(array $assignedToasts): array
    {
        usort($assignedToasts, static function (Toast $left, Toast $right): int {
            if ($left->isBoosted() !== $right->isBoosted()) {
                return $left->isBoosted() ? -1 : 1;
            }

            if (null !== $left->getDueAt() && null !== $right->getDueAt() && $left->getDueAt() != $right->getDueAt()) {
                return $left->getDueAt() <=> $right->getDueAt();
            }

            if (null !== $left->getDueAt() xor null !== $right->getDueAt()) {
                return null !== $left->getDueAt() ? -1 : 1;
            }

            if ($left->getVoteCount() !== $right->getVoteCount()) {
                return $right->getVoteCount() <=> $left->getVoteCount();
            }

            return $left->getCreatedAt() <=> $right->getCreatedAt();
        });

        return $assignedToasts;
    }
}
