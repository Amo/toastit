<?php

namespace App\Tests\Unit;

use App\Entity\Toast;
use App\Entity\User;
use App\Entity\Workspace;
use App\Entity\WorkspaceNote;
use App\Entity\WorkspaceNoteVersion;
use App\Repository\WorkspaceRepository;
use App\Tests\Support\ReflectionHelper;
use App\Workspace\WorkspaceAccessService;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class WorkspaceAccessServiceTest extends TestCase
{
    public function testUserWorkspaceAndItemAccessGuards(): void
    {
        $user = (new User())->setEmail('owner@example.com');
        ReflectionHelper::setId($user, 1);

        $workspace = (new Workspace())
            ->setName('Ops')
            ->setOrganizer($user);
        ReflectionHelper::setId($workspace, 10);

        $item = (new Toast())
            ->setWorkspace($workspace)
            ->setAuthor($user)
            ->setTitle('Toast');
        ReflectionHelper::setId($item, 99);
        $note = (new WorkspaceNote())
            ->setWorkspace($workspace)
            ->setAuthor($user)
            ->applySnapshot('Note', 'Body', false);
        ReflectionHelper::setId($note, 77);
        $version = (new WorkspaceNoteVersion())
            ->setNote($note)
            ->setAuthor($user)
            ->setTitle('Note')
            ->setBody('Body');
        ReflectionHelper::setId($version, 78);

        $workspaceRepository = $this->createMock(WorkspaceRepository::class);
        $workspaceRepository
            ->method('findOneForUser')
            ->willReturnCallback(static fn (int $workspaceId) => 10 === $workspaceId ? $workspace : null);

        $toastRepository = $this->createMock(EntityRepository::class);
        $toastRepository
            ->method('find')
            ->willReturnCallback(static fn (int $itemId) => 99 === $itemId ? $item : null);
        $noteRepository = $this->createMock(EntityRepository::class);
        $noteRepository
            ->method('find')
            ->willReturnCallback(static fn (int $noteId) => 77 === $noteId ? $note : null);
        $versionRepository = $this->createMock(EntityRepository::class);
        $versionRepository
            ->method('find')
            ->willReturnCallback(static fn (int $versionId) => 78 === $versionId ? $version : null);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager
            ->method('getRepository')
            ->willReturnCallback(static function (string $entityClass) use ($toastRepository, $noteRepository, $versionRepository) {
                return match ($entityClass) {
                    Toast::class => $toastRepository,
                    WorkspaceNote::class => $noteRepository,
                    WorkspaceNoteVersion::class => $versionRepository,
                    default => throw new \InvalidArgumentException(sprintf('Unexpected repository for %s', $entityClass)),
                };
            });

        $security = $this->createMock(Security::class);
        $security->method('getUser')->willReturn($user);

        $access = new WorkspaceAccessService($workspaceRepository, $entityManager, $security);

        self::assertSame($user, $access->getUserOrFail());
        self::assertSame($workspace, $access->getWorkspaceOrFail(10));
        self::assertSame($item, $access->getItemOrFail(99));
        self::assertSame($note, $access->getWorkspaceNoteOrFail($workspace, 77));
        self::assertSame($version, $access->getWorkspaceNoteVersionOrFail($note, 78));

        $access->assertOwner($workspace);
        $access->assertMeetingModeIdle($workspace);

        $workspace->startMeetingMode($user, new \DateTimeImmutable('2026-04-02 09:00:00'));
        $access->assertMeetingModeActive($workspace);

        $this->expectException(AccessDeniedHttpException::class);
        $access->assertMeetingModeIdle($workspace);
    }

    public function testAccessThrowsWhenUserWorkspaceOrItemAreMissing(): void
    {
        $workspaceRepository = $this->createMock(WorkspaceRepository::class);
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $security = $this->createMock(Security::class);
        $security->method('getUser')->willReturn(null);

        $access = new WorkspaceAccessService($workspaceRepository, $entityManager, $security);

        try {
            $access->getUserOrFail();
            self::fail('Expected access denied exception.');
        } catch (AccessDeniedHttpException) {
            self::assertTrue(true);
        }

        $user = (new User())->setEmail('user@example.com');
        ReflectionHelper::setId($user, 5);
        $securityWithUser = $this->createMock(Security::class);
        $securityWithUser->method('getUser')->willReturn($user);
        $workspaceRepository->method('findOneForUser')->willReturn(null);
        $accessWithUser = new WorkspaceAccessService($workspaceRepository, $entityManager, $securityWithUser);

        try {
            $accessWithUser->getWorkspaceOrFail(123);
            self::fail('Expected not found exception.');
        } catch (NotFoundHttpException) {
            self::assertTrue(true);
        }

        $toastRepository = $this->createMock(EntityRepository::class);
        $toastRepository->method('find')->willReturn(null);
        $entityManager->method('getRepository')->willReturn($toastRepository);

        $this->expectException(NotFoundHttpException::class);
        $accessWithUser->getItemOrFail(999);
    }
}
