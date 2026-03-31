<?php

namespace App\Meeting;

use App\Entity\ParkingLotItem;

final class MeetingAgenda
{
    /**
     * @param list<ParkingLotItem> $activeItems
     * @param list<ParkingLotItem> $vetoedItems
     */
    public function __construct(
        public readonly array $activeItems,
        public readonly array $vetoedItems,
    ) {
    }
}
