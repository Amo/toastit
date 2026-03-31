<?php

namespace App\Meeting;

use App\Entity\Meeting;
use App\Entity\ParkingLotItem;

final class MeetingAgendaBuilder
{
    public function build(Meeting $meeting): MeetingAgenda
    {
        $activeItems = [];
        $vetoedItems = [];

        foreach ($meeting->getParkingLotItems() as $item) {
            if ($item->isVetoed()) {
                $vetoedItems[] = $item;
                continue;
            }

            $activeItems[] = $item;
        }

        usort($activeItems, $this->compareItems(...));
        usort($vetoedItems, $this->compareVetoedItems(...));

        return new MeetingAgenda($activeItems, $vetoedItems);
    }

    private function compareItems(ParkingLotItem $left, ParkingLotItem $right): int
    {
        if ($left->isBoosted() !== $right->isBoosted()) {
            return $left->isBoosted() ? -1 : 1;
        }

        if ($left->isBoosted() && $right->isBoosted()) {
            return $this->compareNullableInt($left->getBoostRank(), $right->getBoostRank())
                ?: $this->compareCreatedAt($left, $right)
                ?: $this->compareId($left, $right);
        }

        $voteComparison = $right->getVoteCount() <=> $left->getVoteCount();
        if (0 !== $voteComparison) {
            return $voteComparison;
        }

        return $this->compareCreatedAt($left, $right)
            ?: $this->compareId($left, $right);
    }

    private function compareVetoedItems(ParkingLotItem $left, ParkingLotItem $right): int
    {
        return $this->compareCreatedAt($left, $right)
            ?: $this->compareId($left, $right);
    }

    private function compareNullableInt(?int $left, ?int $right): int
    {
        return ($left ?? PHP_INT_MAX) <=> ($right ?? PHP_INT_MAX);
    }

    private function compareCreatedAt(ParkingLotItem $left, ParkingLotItem $right): int
    {
        return $left->getCreatedAt() <=> $right->getCreatedAt();
    }

    private function compareId(ParkingLotItem $left, ParkingLotItem $right): int
    {
        return ($left->getId() ?? PHP_INT_MAX) <=> ($right->getId() ?? PHP_INT_MAX);
    }
}
