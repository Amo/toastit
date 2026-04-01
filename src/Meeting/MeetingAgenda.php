<?php

namespace App\Meeting;

use App\Entity\Toast;

final class MeetingAgenda
{
    /**
     * @param list<Toast> $activeItems
     * @param list<Toast> $vetoedItems
     */
    public function __construct(
        public readonly array $activeItems,
        public readonly array $vetoedItems,
        public readonly array $resolvedItems = [],
    ) {
    }
}
