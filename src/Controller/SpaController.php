<?php

namespace App\Controller;

use App\Security\JwtTokenService;
use App\Security\PinSessionService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class SpaController extends AbstractController
{
    public function __construct(
        private readonly JwtTokenService $jwtTokenManager,
        private readonly PinSessionService $pinSessionService,
    ) {
    }

    #[Route('/{path}', name: 'app_spa', methods: ['GET'], requirements: ['path' => '^(?!api(?:/|$)|build(?:/|$)|styles(?:/|$)|_wdt(?:/|$)|_profiler(?:/|$)).*'], defaults: ['path' => ''])]
    public function __invoke(Request $request): Response
    {
        $flashBag = $request->getSession()->getFlashBag();
        $user = $this->getUser();

        return $this->render('spa.html.twig', [
            'props' => [
                'email' => trim($request->query->getString('email')),
                'isAuthenticated' => null !== $user,
                'accessToken' => $user ? $this->jwtTokenManager->createAccessToken($user, new \DateTimeImmutable()) : null,
                'user' => $user ? [
                    'displayName' => $user->getDisplayName(),
                    'isRoot' => in_array('ROLE_ROOT', $user->getRoles(), true),
                ] : null,
                'pinLockExpiresAt' => $user && $user->hasPin() ? $this->pinSessionService->getExpiresAtTimestamp() : null,
                'loginAction' => $this->generateUrl('app_login_email'),
                'verifyAction' => $this->generateUrl('app_auth_verify'),
                'setupAction' => $this->generateUrl('app_pin_setup'),
                'unlockAction' => $this->generateUrl('app_pin_unlock'),
                'forgotPinAction' => $this->generateUrl('app_pin_forgot'),
                'logoutUrl' => $this->generateUrl('app_logout'),
                'flashes' => [
                    'success' => $flashBag->peek('success'),
                    'error' => $flashBag->peek('error'),
                ],
            ],
        ]);
    }
}
