<?php

namespace App\Tests\Unit;

use App\Api\MyActionsPayloadBuilder;
use App\Entity\Toast;
use App\Entity\ToastComment;
use App\Entity\User;
use App\Entity\Workspace;
use App\Profile\AvatarStorageService;
use App\Profile\AvatarUrlService;
use App\Repository\ToastRepository;
use App\Tests\Support\ReflectionHelper;
use App\Workspace\AssignedToastPriorityService;
use League\Flysystem\FilesystemOperator;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class MyActionsPayloadBuilderTest extends TestCase
{
    public function testBuildReturnsAssignedActionsSummaryAndPayloads(): void
    {
        $user = (new User())->setEmail('owner@example.com')->setFirstName('Owner');
        ReflectionHelper::setId($user, 1);

        $workspace = (new Workspace())->setName('Delivery')->setOrganizer($user);
        ReflectionHelper::setId($workspace, 42);

        $lateAction = (new Toast())
            ->setWorkspace($workspace)
            ->setAuthor($user)
            ->setOwner($user)
            ->setTitle('Late action')
            ->setDescription('Fix the release checklist.')
            ->setDueAt(new \DateTimeImmutable('2000-01-01'));
        ReflectionHelper::setId($lateAction, 100);
        ReflectionHelper::setProperty($lateAction, 'createdAt', new \DateTimeImmutable('2026-04-01 10:00:00'));
        $lateAction->addComment((new ToastComment())->setToast($lateAction)->setAuthor($user)->setContent('Need this before Friday.'));

        $soonAction = (new Toast())
            ->setWorkspace($workspace)
            ->setAuthor($user)
            ->setOwner($user)
            ->setTitle('Soon action')
            ->setDueAt((new \DateTimeImmutable('today'))->modify('+2 days'))
            ->setIsBoosted(true)
            ->setBoostRank(1);
        ReflectionHelper::setId($soonAction, 101);
        ReflectionHelper::setProperty($soonAction, 'createdAt', new \DateTimeImmutable('2026-04-01 09:00:00'));

        $repository = $this->createMock(ToastRepository::class);
        $repository
            ->expects(self::once())
            ->method('findAssignedActiveForUser')
            ->with($user, 200)
            ->willReturn([$lateAction, $soonAction]);

        $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $filesystem = $this->createMock(FilesystemOperator::class);
        $avatarUrl = new AvatarUrlService($urlGenerator, new AvatarStorageService($filesystem, sys_get_temp_dir()));

        $payload = (new MyActionsPayloadBuilder(
            $repository,
            new AssignedToastPriorityService(),
            $avatarUrl,
        ))->build($user);

        self::assertSame(2, $payload['summary']['assignedCount']);
        self::assertSame(1, $payload['summary']['lateCount']);
        self::assertSame(1, $payload['summary']['dueSoonCount']);
        self::assertSame(1, $payload['summary']['workspaceCount']);
        self::assertSame('Soon action', $payload['actions'][0]['title']);
        self::assertTrue($payload['actions'][0]['isBoosted']);
        self::assertSame('Late action', $payload['actions'][1]['title']);
        self::assertTrue($payload['actions'][1]['isLate']);
        self::assertSame('01/01/2000', $payload['actions'][1]['dueOnDisplay']);
        self::assertSame(1, $payload['actions'][1]['commentsCount']);
        self::assertSame('Delivery', $payload['actions'][1]['workspace']['name']);
    }
}
