<?php

namespace App\Tests\Unit;

use App\Entity\User;
use App\Entity\Workspace;
use App\Entity\WorkspaceMember;
use App\Tests\Support\ReflectionHelper;
use App\Workspace\WorkspaceNoteService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class WorkspaceNoteServiceTest extends TestCase
{
    public function testCreateUpdateRevertAndRetentionLifecycle(): void
    {
        $owner = (new User())->setEmail('owner@example.com');
        $member = (new User())->setEmail('member@example.com');
        ReflectionHelper::setId($owner, 1);
        ReflectionHelper::setId($member, 2);

        $workspace = (new Workspace())
            ->setName('Notes')
            ->setOrganizer($owner);
        ReflectionHelper::setId($workspace, 10);
        $workspace->addMembership((new WorkspaceMember())->setUser($member));

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::atLeast(1))->method('persist');
        $entityManager->expects(self::atLeast(1))->method('remove');

        $service = new WorkspaceNoteService($entityManager);

        $note = $service->createNote(
            $workspace,
            $member,
            'Initial title',
            'Initial body',
            false,
            new \DateTimeImmutable('2026-04-21 09:00:00'),
        );

        self::assertSame('Initial title', $note->getTitle());
        self::assertCount(1, $note->getVersions());

        $sameNote = $service->updateNote(
            $note,
            $member,
            'Initial title',
            'Initial body',
            false,
            new \DateTimeImmutable('2026-04-21 09:10:00'),
        );

        self::assertSame($note, $sameNote);
        self::assertCount(1, $note->getVersions());

        $service->updateNote(
            $note,
            $member,
            'Updated title',
            'Updated body',
            true,
            new \DateTimeImmutable('2026-04-21 10:00:00'),
        );

        self::assertSame('Updated title', $note->getTitle());
        self::assertTrue($note->isImportant());
        self::assertCount(2, $note->getVersions());

        $olderVersion = null;
        foreach ($note->getVersions() as $version) {
            if ($version->getTitle() === 'Initial title') {
                $olderVersion = $version;
                break;
            }
        }

        self::assertNotNull($olderVersion);

        $service->revertToVersion(
            $note,
            $olderVersion,
            $owner,
            new \DateTimeImmutable('2026-04-21 11:00:00'),
        );

        self::assertSame('Initial title', $note->getTitle());
        self::assertFalse($note->isImportant());
        self::assertCount(3, $note->getVersions());

        foreach ([
            '2026-04-20 09:00:00',
            '2026-04-19 09:00:00',
            '2026-04-18 09:00:00',
            '2026-04-17 09:00:00',
            '2026-04-16 09:00:00',
            '2026-04-15 09:00:00',
        ] as $index => $timestamp) {
            $service->updateNote(
                $note,
                $member,
                sprintf('Version %d', $index + 1),
                sprintf('Body %d', $index + 1),
                false,
                new \DateTimeImmutable($timestamp),
            );
        }

        $keptDays = array_values(array_unique(array_map(
            static fn ($version) => $version->getRecordedAt()->format('Y-m-d'),
            $note->getVersions()->toArray(),
        )));
        sort($keptDays);

        self::assertSame([
            '2026-04-17',
            '2026-04-18',
            '2026-04-19',
            '2026-04-20',
            '2026-04-21',
        ], $keptDays);
    }

    public function testDeleteGuardsAndRevertOwnershipBoundaries(): void
    {
        $owner = (new User())->setEmail('owner@example.com');
        $author = (new User())->setEmail('author@example.com');
        $outsider = (new User())->setEmail('outsider@example.com');
        ReflectionHelper::setId($owner, 1);
        ReflectionHelper::setId($author, 2);
        ReflectionHelper::setId($outsider, 3);

        $workspace = (new Workspace())
            ->setName('Notes')
            ->setOrganizer($owner);
        ReflectionHelper::setId($workspace, 10);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::once())->method('remove');

        $service = new WorkspaceNoteService($entityManager);
        $note = $service->createNote($workspace, $author, 'Title', 'Body');

        try {
            $service->deleteNote($note, $outsider);
            self::fail('Expected delete access to be denied.');
        } catch (AccessDeniedHttpException) {
            self::assertTrue(true);
        }

        $service->deleteNote($note, $owner);

        $otherWorkspace = (new Workspace())
            ->setName('Other')
            ->setOrganizer($owner);
        ReflectionHelper::setId($otherWorkspace, 11);
        $otherNote = $service->createNote($otherWorkspace, $owner, 'Elsewhere', 'Body');
        $otherVersion = $otherNote->getVersions()->first();

        self::assertNotNull($otherVersion);

        $this->expectException(NotFoundHttpException::class);
        $service->revertToVersion($note, $otherVersion, $owner);
    }

    public function testOwnerCanTransferNoteToAnotherWorkspace(): void
    {
        $owner = (new User())->setEmail('owner@example.com');
        $member = (new User())->setEmail('member@example.com');
        ReflectionHelper::setId($owner, 1);
        ReflectionHelper::setId($member, 2);

        $sourceWorkspace = (new Workspace())
            ->setName('Source')
            ->setOrganizer($owner);
        ReflectionHelper::setId($sourceWorkspace, 10);
        $sourceWorkspace->addMembership((new WorkspaceMember())->setUser($member));

        $targetWorkspace = (new Workspace())
            ->setName('Target')
            ->setOrganizer($owner);
        ReflectionHelper::setId($targetWorkspace, 11);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $service = new WorkspaceNoteService($entityManager);
        $note = $service->createNote($sourceWorkspace, $member, 'Transfer me', 'Body');

        $service->transferNote($note, $targetWorkspace, $owner);

        self::assertSame($targetWorkspace, $note->getWorkspace());
        self::assertCount(0, $sourceWorkspace->getNotes());
        self::assertCount(1, $targetWorkspace->getNotes());
    }

    public function testNonOwnerCannotTransferNote(): void
    {
        $owner = (new User())->setEmail('owner@example.com');
        $member = (new User())->setEmail('member@example.com');
        $outsider = (new User())->setEmail('outsider@example.com');
        ReflectionHelper::setId($owner, 1);
        ReflectionHelper::setId($member, 2);
        ReflectionHelper::setId($outsider, 3);

        $sourceWorkspace = (new Workspace())
            ->setName('Source')
            ->setOrganizer($owner);
        ReflectionHelper::setId($sourceWorkspace, 10);
        $sourceWorkspace->addMembership((new WorkspaceMember())->setUser($member));

        $targetWorkspace = (new Workspace())
            ->setName('Target')
            ->setOrganizer($owner);
        ReflectionHelper::setId($targetWorkspace, 11);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $service = new WorkspaceNoteService($entityManager);
        $note = $service->createNote($sourceWorkspace, $member, 'Transfer me', 'Body');

        $this->expectException(AccessDeniedHttpException::class);
        $service->transferNote($note, $targetWorkspace, $outsider);
    }
}
