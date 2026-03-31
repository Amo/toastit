<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class HomeController extends AbstractController
{
    #[Route('/', name: 'app_home', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $email = trim($request->query->getString('email'));

        return $this->render('home/index.html.twig', [
            'payload' => [
                'email' => $email,
                'submitting' => false,
            ],
        ]);
    }

    #[Route('/connexion', name: 'app_login_email', methods: ['POST'])]
    public function requestLoginLink(Request $request): RedirectResponse
    {
        $email = trim($request->request->getString('email'));

        if ('' === $email || false === filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->addFlash('error', 'Merci de renseigner une adresse email valide.');

            return $this->redirectToRoute('app_home', ['email' => $email]);
        }

        $this->addFlash('success', sprintf(
            'Connexion demandee pour %s. Le flux d\'authentification sera branche a l\'etape suivante.',
            $email
        ));

        return $this->redirectToRoute('app_home', ['email' => $email]);
    }

    #[Route('/design-system', name: 'app_design_system', methods: ['GET'])]
    public function designSystem(): Response
    {
        return $this->render('design_system/index.html.twig');
    }
}
