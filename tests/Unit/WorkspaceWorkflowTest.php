<?php

namespace App\Tests\Unit;

use App\Entity\Toast;
use App\Entity\User;
use App\Entity\Workspace;
use App\Entity\WorkspaceMember;
use App\Tests\Support\ReflectionHelper;
use App\Workspace\WorkspaceWorkflow;
use PHPUnit\Framework\TestCase;

final class WorkspaceWorkflowTest extends TestCase
{
    public function testInviteeQueriesSortAndDeduplicateUsers(): void
    {
        $workflow = new WorkspaceWorkflow();
        $organizer = (new User())->setEmail('zoe@example.com')->setFirstName('Zoe');
        $member = (new User())->setEmail('alice@example.com')->setFirstName('Alice');
        ReflectionHelper::setId($organizer, 10);
        ReflectionHelper::setId($member, 20);

        $workspace = (new Workspace())
            ->setName('Ops')
            ->setOrganizer($organizer);

        $workspace
            ->addMembership((new WorkspaceMember())->setUser($member))
            ->addMembership((new WorkspaceMember())->setUser($organizer));

        $invitees = $workflow->getWorkspaceInvitees($workspace);

        self::assertSame([$member, $organizer], $invitees);
        self::assertSame($member, $workflow->findWorkspaceInviteeById($workspace, 20));
        self::assertNull($workflow->findWorkspaceInviteeById($workspace, 0));
        self::assertNull($workflow->findWorkspaceInviteeById($workspace, 999));
        self::assertSame([
            20 => 'Alice',
            10 => 'Zoe',
        ], $workflow->getWorkspaceInviteeNamesById($workspace));
    }

    public function testNextBoostRankAndFollowUpDetection(): void
    {
        $workflow = new WorkspaceWorkflow();
        $owner = (new User())->setEmail('owner@example.com');
        ReflectionHelper::setId($owner, 7);

        $workspace = (new Workspace())
            ->setName('Ops')
            ->setOrganizer($owner);

        $first = (new Toast())->setTitle('First')->setIsBoosted(true)->setBoostRank(2);
        $second = (new Toast())->setTitle('Second')->setIsBoosted(true)->setBoostRank(5);
        $third = (new Toast())->setTitle('Third')->setIsBoosted(false);

        $workspace->addItem($first)->addItem($second)->addItem($third);

        self::assertSame(6, $workflow->nextBoostRank($workspace));

        $parent = (new Toast())->setTitle('Parent');
        $matchingChild = (new Toast())
            ->setTitle('Follow-up')
            ->setOwner($owner)
            ->setDueAt(new \DateTimeImmutable('2026-04-10'));
        $nonMatchingChild = (new Toast())
            ->setTitle('Other')
            ->setOwner(null)
            ->setDueAt(null);

        ReflectionHelper::setProperty($parent, 'followUpChildren', new \Doctrine\Common\Collections\ArrayCollection([$matchingChild, $nonMatchingChild]));

        self::assertTrue($workflow->hasFollowUp($parent, 'Follow-up', $owner, new \DateTimeImmutable('2026-04-10')));
        self::assertFalse($workflow->hasFollowUp($parent, 'Follow-up', null, new \DateTimeImmutable('2026-04-10')));
    }
}
