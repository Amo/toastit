<?php

namespace App\Tests\Unit;

use App\Api\DashboardPayloadBuilder;
use App\Entity\Toast;
use App\Entity\User;
use App\Entity\Workspace;
use App\Entity\WorkspaceMember;
use App\Repository\WorkspaceRepository;
use App\Tests\Support\ReflectionHelper;
use PHPUnit\Framework\TestCase;

final class DashboardPayloadBuilderTest extends TestCase
{
    public function testBuildReturnsWorkspaceSummariesWithCounts(): void
    {
        $user = (new User())->setEmail('owner@example.com');
        ReflectionHelper::setId($user, 1);

        $workspace = (new Workspace())
            ->setName('Delivery')
            ->setOrganizer($user);
        ReflectionHelper::setId($workspace, 42);
        $workspace->setMeetingStartedAt(new \DateTimeImmutable('2026-04-02 09:00:00'));
        $workspace
            ->addMembership((new WorkspaceMember())->setUser($user)->setIsOwner(true))
            ->addMembership((new WorkspaceMember())->setUser((new User())->setEmail('guest@example.com')));

        $workspace
            ->addItem((new Toast())->setTitle('Open')->setDiscussionStatus(Toast::DISCUSSION_PENDING))
            ->addItem((new Toast())->setTitle('Resolved')->setDiscussionStatus(Toast::DISCUSSION_TREATED));

        $repository = $this->createMock(WorkspaceRepository::class);
        $repository
            ->expects(self::once())
            ->method('findForUser')
            ->with($user)
            ->willReturn([$workspace]);

        $builder = new DashboardPayloadBuilder($repository);

        self::assertSame([
            'workspaces' => [[
                'id' => 42,
                'name' => 'Delivery',
                'isDefault' => false,
                'meetingMode' => Workspace::MEETING_MODE_IDLE,
                'meetingStartedAt' => '2026-04-02T09:00:00+00:00',
                'memberCount' => 2,
                'openItemCount' => 1,
                'resolvedItemCount' => 1,
            ]],
        ], $builder->build($user));
    }
}
