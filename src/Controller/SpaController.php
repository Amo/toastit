<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class SpaController extends AbstractController
{
    public function __construct(
        private readonly ?string $recaptchaSiteKey,
    ) {
    }

    #[Route('/{path}', name: 'app_spa', methods: ['GET'], requirements: ['path' => '^(?!api(?:/|$)|build(?:/|$)|styles(?:/|$)|_wdt(?:/|$)|_profiler(?:/|$)).*'], defaults: ['path' => ''])]
    public function __invoke(Request $request): Response
    {
        $flashBag = $request->getSession()->getFlashBag();

        return $this->render('spa.html.twig', [
            'props' => [
                'email' => trim($request->query->getString('email')),
                'isAuthenticated' => false,
                'accessToken' => null,
                'user' => null,
                'pinLockExpiresAt' => null,
                'recaptchaSiteKey' => (string) $this->recaptchaSiteKey,
                'flashes' => [
                    'success' => $flashBag->get('success'),
                    'error' => $flashBag->get('error'),
                ],
            ],
        ]);
    }
}
