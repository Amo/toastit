<?php

namespace App\Meeting;

use App\Entity\Toast;
use App\Entity\Workspace;

final class MeetingAgendaBuilder
{
    public function build(Workspace $workspace): MeetingAgenda
    {
        $activeItems = [];
        $vetoedItems = [];
        $resolvedItems = [];

        foreach ($workspace->getItems() as $item) {
            if (Toast::DISCUSSION_TREATED === $item->getDiscussionStatus()) {
                $resolvedItems[] = $item;
                continue;
            }

            if ($item->isVetoed()) {
                $vetoedItems[] = $item;
                continue;
            }

            $activeItems[] = $item;
        }

        usort($activeItems, $this->compareItems(...));
        usort($vetoedItems, $this->compareVetoedItems(...));
        usort($resolvedItems, $this->compareResolvedItems(...));

        return new MeetingAgenda($activeItems, $vetoedItems, $resolvedItems);
    }

    private function compareItems(Toast $left, Toast $right): int
    {
        if ($left->isBoosted() !== $right->isBoosted()) {
            return $left->isBoosted() ? -1 : 1;
        }

        $createdAtComparison = $this->compareCreatedAt($left, $right);
        if (0 !== $createdAtComparison) {
            return $createdAtComparison;
        }

        $voteComparison = $right->getVoteCount() <=> $left->getVoteCount();
        if (0 !== $voteComparison) {
            return $voteComparison;
        }

        return $this->compareId($left, $right);
    }

    private function compareVetoedItems(Toast $left, Toast $right): int
    {
        return $this->compareCreatedAt($left, $right)
            ?: $this->compareId($left, $right);
    }

    private function compareResolvedItems(Toast $left, Toast $right): int
    {
        return $right->getCreatedAt() <=> $left->getCreatedAt()
            ?: (($right->getId() ?? 0) <=> ($left->getId() ?? 0));
    }

    private function compareCreatedAt(Toast $left, Toast $right): int
    {
        return $left->getCreatedAt() <=> $right->getCreatedAt();
    }

    private function compareId(Toast $left, Toast $right): int
    {
        return ($left->getId() ?? PHP_INT_MAX) <=> ($right->getId() ?? PHP_INT_MAX);
    }
}
