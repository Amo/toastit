<?php

namespace App\Tests\Unit;

use App\Workspace\InboundReplyAddressService;
use PHPUnit\Framework\TestCase;

final class InboundReplyAddressServiceTest extends TestCase
{
    public function testParseAddressSupportsDisplayNameAndCaseInsensitiveMailbox(): void
    {
        $service = new InboundReplyAddressService('in.toastit.cc');

        self::assertSame(
            [
                'selector' => 'a1b2c3d4e5f60708',
                'token' => 'cafebabedeadbeefcafebabedeadbeef',
            ],
            $service->parseAddress('Toast Reply <TOAST-REPLY+A1B2C3D4E5F60708-CAFEBABEDEADBEEFCAFEBABEDEADBEEF@IN.TOASTIT.CC>'),
        );
    }
}
