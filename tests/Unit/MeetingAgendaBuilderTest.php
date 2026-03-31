<?php

namespace App\Tests\Unit;

use App\Entity\Meeting;
use App\Entity\ParkingLotItem;
use App\Entity\User;
use App\Entity\Vote;
use App\Meeting\MeetingAgendaBuilder;
use PHPUnit\Framework\TestCase;

final class MeetingAgendaBuilderTest extends TestCase
{
    public function testBuildOrdersBoostedItemsFirstAndSeparatesVetoedItems(): void
    {
        $meeting = (new Meeting())
            ->setOrganizer((new User())->setEmail('owner@example.com'))
            ->setTitle('Weekly sync');

        $normal = (new ParkingLotItem())
            ->setMeeting($meeting)
            ->setAuthor((new User())->setEmail('normal@example.com'))
            ->setTitle('Normal');

        $boosted = (new ParkingLotItem())
            ->setMeeting($meeting)
            ->setAuthor((new User())->setEmail('boosted@example.com'))
            ->setTitle('Boosted')
            ->setIsBoosted(true)
            ->setBoostRank(1);

        $vetoed = (new ParkingLotItem())
            ->setMeeting($meeting)
            ->setAuthor((new User())->setEmail('vetoed@example.com'))
            ->setTitle('Vetoed')
            ->setStatus(ParkingLotItem::STATUS_VETOED);

        $normal->addVote((new Vote())->setItem($normal)->setUser((new User())->setEmail('vote-1@example.com')));
        $normal->addVote((new Vote())->setItem($normal)->setUser((new User())->setEmail('vote-2@example.com')));

        $meeting->getParkingLotItems()->add($normal);
        $meeting->getParkingLotItems()->add($boosted);
        $meeting->getParkingLotItems()->add($vetoed);

        $agenda = (new MeetingAgendaBuilder())->build($meeting);

        self::assertSame(['Boosted', 'Normal'], array_map(
            static fn (ParkingLotItem $item): string => $item->getTitle(),
            $agenda->activeItems
        ));
        self::assertSame(['Vetoed'], array_map(
            static fn (ParkingLotItem $item): string => $item->getTitle(),
            $agenda->vetoedItems
        ));
    }
}
