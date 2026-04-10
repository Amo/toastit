<?php

namespace App\Tests\Unit;

use App\Entity\PersonalAccessToken;
use App\Repository\PersonalAccessTokenRepository;
use App\Security\PersonalAccessTokenService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

final class PersonalAccessTokenServiceTest extends TestCase
{
    public function testFindActiveByPlainTextAcceptsToastitPrefix(): void
    {
        $token = 'toastit_c28eb351c111f7eb_9c6e7f1002d6ba096996bc23a551b2c18f74a98685e805af';
        $candidate = (new PersonalAccessToken())
            ->setSelector('c28eb351c111f7eb')
            ->setTokenHash(hash('sha256', $token));

        $repository = $this->createMock(PersonalAccessTokenRepository::class);
        $repository
            ->expects(self::once())
            ->method('findActiveBySelector')
            ->with('c28eb351c111f7eb', self::isInstanceOf(\DateTimeImmutable::class))
            ->willReturn([$candidate]);

        $service = new PersonalAccessTokenService(
            $this->createMock(EntityManagerInterface::class),
            $repository
        );

        self::assertSame($candidate, $service->findActiveByPlainText($token));
    }

    public function testFindActiveByPlainTextAcceptsAnyPrefix(): void
    {
        $token = 'nextgen_pat_v3_c28eb351c111f7eb_9c6e7f1002d6ba096996bc23a551b2c18f74a98685e805af';
        $candidate = (new PersonalAccessToken())
            ->setSelector('c28eb351c111f7eb')
            ->setTokenHash(hash('sha256', $token));

        $repository = $this->createMock(PersonalAccessTokenRepository::class);
        $repository
            ->expects(self::once())
            ->method('findActiveBySelector')
            ->with('c28eb351c111f7eb', self::isInstanceOf(\DateTimeImmutable::class))
            ->willReturn([$candidate]);

        $service = new PersonalAccessTokenService(
            $this->createMock(EntityManagerInterface::class),
            $repository
        );

        self::assertSame($candidate, $service->findActiveByPlainText($token));
    }

    public function testFindActiveByPlainTextAcceptsNoPrefix(): void
    {
        $token = 'c28eb351c111f7eb_9c6e7f1002d6ba096996bc23a551b2c18f74a98685e805af';
        $candidate = (new PersonalAccessToken())
            ->setSelector('c28eb351c111f7eb')
            ->setTokenHash(hash('sha256', $token));

        $repository = $this->createMock(PersonalAccessTokenRepository::class);
        $repository
            ->expects(self::once())
            ->method('findActiveBySelector')
            ->with('c28eb351c111f7eb', self::isInstanceOf(\DateTimeImmutable::class))
            ->willReturn([$candidate]);

        $service = new PersonalAccessTokenService(
            $this->createMock(EntityManagerInterface::class),
            $repository
        );

        self::assertSame($candidate, $service->findActiveByPlainText($token));
    }
}
