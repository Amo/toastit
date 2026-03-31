<?php

namespace App\EventSubscriber;

use App\Entity\User;
use App\Security\PinSessionManager;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class PinLockSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly Security $security,
        private readonly PinSessionManager $pinSessionManager,
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 10],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();

        if (!str_starts_with($request->getPathInfo(), '/app')) {
            return;
        }

        $user = $this->security->getUser();

        if (!$user instanceof User) {
            return;
        }

        if (!$user->hasPin()) {
            $event->setResponse(new RedirectResponse($this->urlGenerator->generate('app_pin_setup')));

            return;
        }

        if (!$this->pinSessionManager->isVerified()) {
            $this->pinSessionManager->clear();
            $event->setResponse(new RedirectResponse($this->urlGenerator->generate('app_pin_unlock')));
        }
    }
}
