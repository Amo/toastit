<?php

namespace App\Tests\Unit;

use App\Entity\User;
use App\Security\PinManager;
use PHPUnit\Framework\TestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class PinManagerTest extends TestCase
{
    public function testHashAndVerifyPin(): void
    {
        $user = (new User())->setEmail('user@example.com')->setPinHash('stored-hash');
        $hasher = $this->createMock(UserPasswordHasherInterface::class);
        $hasher
            ->expects(self::once())
            ->method('hashPassword')
            ->with($user, '1234')
            ->willReturn('hashed-pin');
        $hasher
            ->expects(self::once())
            ->method('isPasswordValid')
            ->with($user, '1234')
            ->willReturn(true);

        $manager = new PinManager($hasher);

        self::assertSame('hashed-pin', $manager->hashPin($user, '1234'));
        self::assertTrue($manager->verifyPin($user, '1234'));

        $user->setPinHash(null);
        self::assertFalse($manager->verifyPin($user, '1234'));
    }
}
