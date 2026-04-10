<?php

namespace App\Tests\Unit;

use App\Api\DashboardPayloadBuilder;
use App\Api\MyActionsPayloadBuilder;
use App\Entity\Toast;
use App\Entity\User;
use App\Entity\Workspace;
use App\Entity\WorkspaceMember;
use App\Profile\AvatarStorageService;
use App\Profile\AvatarUrlService;
use App\Repository\ToastRepository;
use App\Repository\WorkspaceRepository;
use App\Tests\Support\ReflectionHelper;
use App\Workspace\AssignedToastPriorityService;
use League\Flysystem\FilesystemOperator;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

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
        $guest = (new User())->setEmail('guest@example.com')->setFirstName('Guest');
        ReflectionHelper::setId($guest, 2);
        $workspace
            ->addMembership((new WorkspaceMember())->setUser($user)->setIsOwner(true))
            ->addMembership((new WorkspaceMember())->setUser($guest));

        $workspace
            ->addItem((new Toast())->setTitle('Open')->setStatus(Toast::STATUS_PENDING)->setOwner($user)->setDueAt(new \DateTimeImmutable('2000-01-01')))
            ->addItem((new Toast())->setTitle('Resolved')->setStatus(Toast::STATUS_TOASTED));

        $assignedAction = (new Toast())
            ->setWorkspace($workspace)
            ->setAuthor($user)
            ->setOwner($user)
            ->setTitle('Assigned action')
            ->setDueAt(new \DateTimeImmutable('2000-01-01'));
        ReflectionHelper::setId($assignedAction, 88);

        $repository = $this->createMock(WorkspaceRepository::class);
        $repository
            ->expects(self::once())
            ->method('findForUser')
            ->with($user)
            ->willReturn([$workspace]);

        $toastRepository = $this->createMock(ToastRepository::class);
        $toastRepository
            ->expects(self::once())
            ->method('findAssignedActiveForUser')
            ->with($user, 200)
            ->willReturn([$assignedAction]);

        $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $filesystem = $this->createMock(FilesystemOperator::class);
        $avatarUrl = new AvatarUrlService($urlGenerator, new AvatarStorageService($filesystem, sys_get_temp_dir()));

        $builder = new DashboardPayloadBuilder(
            $repository,
            $avatarUrl,
            new MyActionsPayloadBuilder($toastRepository, new AssignedToastPriorityService(), $avatarUrl),
        );

        self::assertSame([
            'myActions' => [
                'summary' => [
                    'assignedCount' => 1,
                    'lateCount' => 1,
                    'dueSoonCount' => 0,
                    'workspaceCount' => 1,
                ],
                'actions' => [[
                    'id' => 88,
                    'title' => 'Assigned action',
                    'description' => null,
                    'voteCount' => 0,
                    'isBoosted' => false,
                    'isLate' => true,
                    'isDueSoon' => false,
                    'dueOn' => '2000-01-01',
                    'dueOnDisplay' => '01/01/2000',
                    'createdAt' => $assignedAction->getCreatedAt()->format(\DateTimeInterface::ATOM),
                    'createdAtDisplay' => $assignedAction->getCreatedAt()->format('d/m/Y H:i'),
                    'commentsCount' => 0,
                    'workspace' => [
                        'id' => 42,
                        'name' => 'Delivery',
                        'isSoloWorkspace' => false,
                        'isInboxWorkspace' => false,
                    ],
                    'author' => [
                        'id' => 1,
                        'displayName' => 'owner@example.com',
                        'initials' => 'OW',
                        'gravatarUrl' => $user->getGravatarUrl(),
                    ],
                    'owner' => [
                        'id' => 1,
                        'displayName' => 'owner@example.com',
                        'initials' => 'OW',
                        'gravatarUrl' => $user->getGravatarUrl(),
                    ],
                ]],
            ],
            'workspaces' => [[
                'id' => 42,
                'name' => 'Delivery',
                'isDefault' => false,
                'isSoloWorkspace' => false,
                'currentUserIsOwner' => true,
                'meetingMode' => Workspace::MEETING_MODE_IDLE,
                'meetingStartedAt' => '2026-04-02T09:00:00+00:00',
                'memberCount' => 2,
                'openItemCount' => 1,
                'resolvedItemCount' => 1,
                'assignedOpenItemCount' => 1,
                'assignedLateOpenItemCount' => 1,
                'lateOpenItemCount' => 1,
                'membersPreview' => [
                    [
                        'id' => 2,
                        'displayName' => 'Guest',
                        'email' => 'guest@example.com',
                        'initials' => 'G',
                        'gravatarUrl' => $guest->getGravatarUrl(),
                    ],
                    [
                        'id' => 1,
                        'displayName' => 'owner@example.com',
                        'email' => 'owner@example.com',
                        'initials' => 'OW',
                        'gravatarUrl' => $user->getGravatarUrl(),
                    ],
                ],
            ]],
        ], $builder->build($user));
    }
}
