<?php

namespace App\Tests\Unit;

use App\Entity\Toast;
use App\Entity\User;
use App\Entity\Vote;
use App\Entity\Workspace;
use App\Tests\Support\ReflectionHelper;
use App\Workspace\AssignedToastPriorityService;
use PHPUnit\Framework\TestCase;

final class AssignedToastPriorityServiceTest extends TestCase
{
    public function testSortPrioritizesBoostedThenDueDateThenVotes(): void
    {
        $user = (new User())->setEmail('owner@example.com');
        $workspace = (new Workspace())->setName('Delivery')->setOrganizer($user);

        $laterBoosted = (new Toast())
            ->setWorkspace($workspace)
            ->setAuthor($user)
            ->setTitle('Later boosted')
            ->setOwner($user)
            ->setDueAt(new \DateTimeImmutable('2026-04-15'))
            ->setIsBoosted(true)
            ->setBoostRank(2);
        ReflectionHelper::setProperty($laterBoosted, 'createdAt', new \DateTimeImmutable('2026-04-01 11:00:00'));

        $earlierDue = (new Toast())
            ->setWorkspace($workspace)
            ->setAuthor($user)
            ->setTitle('Earlier due')
            ->setOwner($user)
            ->setDueAt(new \DateTimeImmutable('2026-04-11'));
        ReflectionHelper::setProperty($earlierDue, 'createdAt', new \DateTimeImmutable('2026-04-01 09:00:00'));

        $highVotes = (new Toast())
            ->setWorkspace($workspace)
            ->setAuthor($user)
            ->setTitle('High votes')
            ->setOwner($user);
        ReflectionHelper::setProperty($highVotes, 'createdAt', new \DateTimeImmutable('2026-04-01 08:00:00'));
        $highVotes
            ->addVote((new Vote())->setItem($highVotes)->setUser($user))
            ->addVote((new Vote())->setItem($highVotes)->setUser((new User())->setEmail('voter-1@example.com')))
            ->addVote((new Vote())->setItem($highVotes)->setUser((new User())->setEmail('voter-2@example.com')))
            ->addVote((new Vote())->setItem($highVotes)->setUser((new User())->setEmail('voter-3@example.com')));

        $lowVotes = (new Toast())
            ->setWorkspace($workspace)
            ->setAuthor($user)
            ->setTitle('Low votes')
            ->setOwner($user);
        ReflectionHelper::setProperty($lowVotes, 'createdAt', new \DateTimeImmutable('2026-04-01 07:00:00'));
        $lowVotes->addVote((new Vote())->setItem($lowVotes)->setUser((new User())->setEmail('voter-4@example.com')));

        $sorted = (new AssignedToastPriorityService())->sort([$lowVotes, $earlierDue, $laterBoosted, $highVotes]);

        self::assertSame(
            ['Later boosted', 'Earlier due', 'High votes', 'Low votes'],
            array_map(static fn (Toast $toast): string => $toast->getTitle(), $sorted),
        );
    }
}
