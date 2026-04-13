<?php

namespace App\Tests\Unit;

use App\Security\PinSessionService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;

final class PinSessionServiceTest extends TestCase
{
    public function testMarkVerifyAndClearLifecycle(): void
    {
        $session = new Session(new MockArraySessionStorage());
        $request = new Request();
        $request->setSession($session);
        $requestStack = new RequestStack();
        $requestStack->push($request);

        $manager = new PinSessionService($requestStack);

        self::assertFalse($manager->isVerified());

        $manager->markVerified();
        self::assertTrue($manager->isVerified());

        $manager->clear();
        self::assertFalse($manager->isVerified());
    }

    public function testStoredVerificationRemainsValid(): void
    {
        $session = new Session(new MockArraySessionStorage());
        $session->set('security.pin_verified_at', time() - 3600);
        $request = new Request();
        $request->setSession($session);
        $requestStack = new RequestStack();
        $requestStack->push($request);

        $manager = new PinSessionService($requestStack);

        self::assertTrue($manager->isVerified());
    }
}
