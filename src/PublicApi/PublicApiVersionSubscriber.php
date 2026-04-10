<?php

namespace App\PublicApi;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

final class PublicApiVersionSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly PublicApiVersionService $publicApiVersionService,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 20],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();

        $routeName = (string) $request->attributes->get('_route', '');
        $isPublicApiRoute = str_starts_with($routeName, 'public_api_');
        $isPublicApiHost = $this->publicApiVersionService->isPublicApiHost((string) $request->getHost());

        if ($isPublicApiRoute && !$isPublicApiHost) {
            $event->setResponse(new Response('', 404));
            return;
        }

        if (!$isPublicApiHost) {
            return;
        }

        if ('public_api_doc' === $routeName) {
            return;
        }

        $acceptHeader = (string) $request->headers->get('Accept');

        if ($this->publicApiVersionService->isSupportedAcceptHeader($acceptHeader)) {
            return;
        }

        $event->setResponse(new JsonResponse([
            'ok' => false,
            'error' => 'unsupported_api_version',
            'expectedAccept' => $this->publicApiVersionService->buildExpectedAcceptValue(),
        ], 406));
    }
}
