<?php

namespace App\Tests\Unit;

use App\Entity\User;
use App\Workspace\InboundEmailAddressService;
use PHPUnit\Framework\TestCase;

final class InboundEmailAddressServiceTest extends TestCase
{
    public function testBuildAddressForUserUsesStoredUuidV7Alias(): void
    {
        $user = (new User())->setEmail('amaury@lerouxdelens.com');
        $user->setInboundEmailAlias('0195f2f7-89ab-7cde-8fab-0123456789ab');

        $service = new InboundEmailAddressService('in.toastit.cc');
        $address = $service->buildAddressForUser($user);

        self::assertNotNull($address);
        self::assertSame(mb_strtolower($address), $address);
        self::assertSame('toast+0195f2f7-89ab-7cde-8fab-0123456789ab@in.toastit.cc', $address);
        self::assertSame('0195f2f7-89ab-7cde-8fab-0123456789ab', $service->resolveUserAlias($address));
        self::assertNull($service->resolveUserEmail($address));
    }

    public function testResolveUserEmailSupportsLegacyBase64Alias(): void
    {
        $service = new InboundEmailAddressService('in.toastit.cc');

        self::assertSame(
            'amaury@lerouxdelens.com',
            $service->resolveUserEmail('toast+YW1hdXJ5QGxlcm91eGRlbGVucy5jb20@in.toastit.cc'),
        );
    }
}
