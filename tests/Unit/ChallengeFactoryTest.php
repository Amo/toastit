<?php

namespace App\Tests\Unit;

use App\Entity\LoginChallenge;
use App\Entity\User;
use App\Security\ChallengeFactory;
use PHPUnit\Framework\TestCase;

final class ChallengeFactoryTest extends TestCase
{
    public function testFactoryBuildsAlphanumericCodeAndTokenizedChallenge(): void
    {
        $factory = new ChallengeFactory();
        $user = (new User())->setEmail('factory@example.com');
        $created = $factory->create($user, LoginChallenge::PURPOSE_LOGIN, new \DateTimeImmutable('2026-03-31 10:00:00'));

        self::assertSame($user, $created->challenge->getUser());
        self::assertSame(LoginChallenge::PURPOSE_LOGIN, $created->challenge->getPurpose());
        self::assertMatchesRegularExpression('/^[A-Z0-9]{6}$/', $created->challenge->getCode());
        self::assertSame(32, strlen($created->plainToken));
        self::assertSame(hash('sha256', $created->plainToken), $created->challenge->getTokenHash());
    }
}
