<?php

namespace App\Tests\Unit;

use App\Entity\Toast;
use App\Entity\User;
use App\Entity\Vote;
use App\Entity\Workspace;
use App\Meeting\MeetingAgendaBuilder;
use PHPUnit\Framework\TestCase;

final class MeetingAgendaBuilderTest extends TestCase
{
    public function testBuildOrdersBoostedItemsFirstSeparatesVetoedAndResolvedItems(): void
    {
        $workspace = (new Workspace())
            ->setOrganizer((new User())->setEmail('owner@example.com'))
            ->setName('Weekly sync');

        $normal = (new Toast())
            ->setWorkspace($workspace)
            ->setAuthor((new User())->setEmail('normal@example.com'))
            ->setTitle('Normal');
        $this->setCreatedAt($normal, '2026-04-01 09:30:00');

        $boosted = (new Toast())
            ->setWorkspace($workspace)
            ->setAuthor((new User())->setEmail('boosted@example.com'))
            ->setTitle('Boosted')
            ->setIsBoosted(true)
            ->setBoostRank(1);
        $this->setCreatedAt($boosted, '2026-04-01 10:00:00');

        $boostedOlder = (new Toast())
            ->setWorkspace($workspace)
            ->setAuthor((new User())->setEmail('boosted-older@example.com'))
            ->setTitle('Boosted older')
            ->setIsBoosted(true);
        $this->setCreatedAt($boostedOlder, '2026-04-01 08:00:00');

        $vetoed = (new Toast())
            ->setWorkspace($workspace)
            ->setAuthor((new User())->setEmail('vetoed@example.com'))
            ->setTitle('Vetoed')
            ->setStatus(Toast::STATUS_VETOED);

        $resolved = (new Toast())
            ->setWorkspace($workspace)
            ->setAuthor((new User())->setEmail('resolved@example.com'))
            ->setTitle('Resolved')
            ->setDiscussionStatus(Toast::DISCUSSION_TREATED);

        $normal->addVote((new Vote())->setItem($normal)->setUser((new User())->setEmail('vote-1@example.com')));
        $normal->addVote((new Vote())->setItem($normal)->setUser((new User())->setEmail('vote-2@example.com')));

        $olderLessVoted = (new Toast())
            ->setWorkspace($workspace)
            ->setAuthor((new User())->setEmail('older@example.com'))
            ->setTitle('Older less voted');
        $this->setCreatedAt($olderLessVoted, '2026-04-01 07:00:00');
        $olderLessVoted->addVote((new Vote())->setItem($olderLessVoted)->setUser((new User())->setEmail('vote-older@example.com')));

        $workspace->getItems()->add($normal);
        $workspace->getItems()->add($boosted);
        $workspace->getItems()->add($boostedOlder);
        $workspace->getItems()->add($olderLessVoted);
        $workspace->getItems()->add($vetoed);
        $workspace->getItems()->add($resolved);

        $agenda = (new MeetingAgendaBuilder())->build($workspace);

        self::assertSame(['Boosted older', 'Boosted', 'Older less voted', 'Normal'], array_map(
            static fn (Toast $item): string => $item->getTitle(),
            $agenda->activeItems
        ));
        self::assertSame(['Vetoed'], array_map(
            static fn (Toast $item): string => $item->getTitle(),
            $agenda->vetoedItems
        ));
        self::assertSame(['Resolved'], array_map(
            static fn (Toast $item): string => $item->getTitle(),
            $agenda->resolvedItems
        ));
    }

    private function setCreatedAt(Toast $item, string $dateTime): void
    {
        $reflection = new \ReflectionProperty($item, 'createdAt');
        $reflection->setValue($item, new \DateTimeImmutable($dateTime));
    }
}
