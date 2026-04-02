<?php

namespace App\Tests\Unit;

use App\Entity\User;
use App\Security\EmailNormalizerService;
use App\Workspace\WorkspaceUserService;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

final class WorkspaceUserServiceTest extends TestCase
{
    public function testFindOrCreateUserByEmailReturnsExistingUser(): void
    {
        $existingUser = (new User())->setEmail('existing@example.com');
        $repository = $this->createMock(EntityRepository::class);
        $repository
            ->expects(self::once())
            ->method('findOneBy')
            ->with(['email' => 'existing@example.com'])
            ->willReturn($existingUser);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager
            ->method('getRepository')
            ->with(User::class)
            ->willReturn($repository);
        $entityManager->expects(self::never())->method('persist');
        $entityManager->expects(self::never())->method('flush');

        $provisioner = new WorkspaceUserService($entityManager, new EmailNormalizerService());

        self::assertSame($existingUser, $provisioner->findOrCreateUserByEmail(' Existing@example.com '));
    }

    public function testFindOrCreateUserByEmailCreatesUserAndDefaultWorkspace(): void
    {
        $repository = $this->createMock(EntityRepository::class);
        $repository
            ->method('findOneBy')
            ->willReturn(null);

        $persisted = [];
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager
            ->method('getRepository')
            ->with(User::class)
            ->willReturn($repository);
        $entityManager
            ->expects(self::exactly(3))
            ->method('persist')
            ->willReturnCallback(static function (object $entity) use (&$persisted): void {
                $persisted[] = $entity;
            });
        $entityManager->expects(self::once())->method('flush');

        $provisioner = new WorkspaceUserService($entityManager, new EmailNormalizerService());
        $user = $provisioner->findOrCreateUserByEmail(' New@example.com ');

        self::assertSame('new@example.com', $user->getEmail());
        self::assertCount(3, $persisted);
        self::assertSame('My Toasts', $persisted[0]->getName());
        self::assertTrue($persisted[0]->isDefault());
        self::assertTrue($persisted[0]->isSoloWorkspace());
        self::assertSame($user, $persisted[1]->getUser());
        self::assertTrue($persisted[1]->isOwner());
        self::assertSame($user, $persisted[2]);
    }
}
