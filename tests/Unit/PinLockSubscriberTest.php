<?php

namespace App\Tests\Unit;

use App\Entity\User;
use App\EventSubscriber\PinLockSubscriber;
use App\Security\PinSessionService;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class PinLockSubscriberTest extends TestCase
{
    public function testSubscriberConfigurationAndNonAppRequestsDoNothing(): void
    {
        self::assertArrayHasKey(\Symfony\Component\HttpKernel\KernelEvents::REQUEST, PinLockSubscriber::getSubscribedEvents());

        $subscriber = new PinLockSubscriber(
            $this->createMock(Security::class),
            new PinSessionService(new RequestStack()),
            $this->createMock(UrlGeneratorInterface::class),
        );

        $event = new RequestEvent(
            $this->createMock(HttpKernelInterface::class),
            Request::create('/health'),
            HttpKernelInterface::MAIN_REQUEST
        );

        $subscriber->onKernelRequest($event);

        self::assertNull($event->getResponse());
    }

    public function testSubscriberRedirectsToSetupWhenUserHasNoPin(): void
    {
        $user = (new User())->setEmail('user@example.com');
        $security = $this->createMock(Security::class);
        $security->method('getUser')->willReturn($user);
        $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $urlGenerator->method('generate')->with('app_pin_setup')->willReturn('/pin/setup');

        $subscriber = new PinLockSubscriber($security, $this->createPinSessionService(), $urlGenerator);
        $event = new RequestEvent(
            $this->createMock(HttpKernelInterface::class),
            Request::create('/app'),
            HttpKernelInterface::MAIN_REQUEST
        );

        $subscriber->onKernelRequest($event);

        self::assertSame('/pin/setup', $event->getResponse()?->headers->get('Location'));
    }

    public function testSubscriberRedirectsToUnlockWhenPinIsNotVerified(): void
    {
        $user = (new User())->setEmail('user@example.com')->setPinHash('hash');
        $security = $this->createMock(Security::class);
        $security->method('getUser')->willReturn($user);

        [$pinSessionManager, $session] = $this->createPinSessionServiceWithSession();
        $session->set('security.pin_verified_at', time() - 4000);

        $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $urlGenerator->method('generate')->with('app_pin_unlock')->willReturn('/pin/unlock');

        $subscriber = new PinLockSubscriber($security, $pinSessionManager, $urlGenerator);
        $event = new RequestEvent(
            $this->createMock(HttpKernelInterface::class),
            Request::create('/app/profile'),
            HttpKernelInterface::MAIN_REQUEST
        );

        $subscriber->onKernelRequest($event);

        self::assertSame('/pin/unlock', $event->getResponse()?->headers->get('Location'));
        self::assertFalse($session->has('security.pin_verified_at'));
    }

    public function testSubscriberLetsVerifiedPinnedUsersPass(): void
    {
        $user = (new User())->setEmail('user@example.com')->setPinHash('hash');
        $security = $this->createMock(Security::class);
        $security->method('getUser')->willReturn($user);

        [$pinSessionManager, $session] = $this->createPinSessionServiceWithSession();
        $session->set('security.pin_verified_at', time());

        $subscriber = new PinLockSubscriber(
            $security,
            $pinSessionManager,
            $this->createMock(UrlGeneratorInterface::class),
        );

        $event = new RequestEvent(
            $this->createMock(HttpKernelInterface::class),
            Request::create('/app/workspaces/1'),
            HttpKernelInterface::MAIN_REQUEST
        );

        $subscriber->onKernelRequest($event);

        self::assertNull($event->getResponse());
    }

    private function createPinSessionService(): PinSessionService
    {
        return $this->createPinSessionServiceWithSession()[0];
    }

    /**
     * @return array{0: PinSessionService, 1: Session}
     */
    private function createPinSessionServiceWithSession(): array
    {
        $session = new Session(new MockArraySessionStorage());
        $request = new Request();
        $request->setSession($session);
        $requestStack = new RequestStack();
        $requestStack->push($request);

        return [new PinSessionService($requestStack), $session];
    }
}
