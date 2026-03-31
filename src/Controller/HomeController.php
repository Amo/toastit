<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class HomeController extends AbstractController
{
    #[Route('/', name: 'app_home', methods: ['GET'])]
    public function index(Request $request): Response
    {
        return $this->render('home/index.html.twig', [
            'payload' => [
                'email' => trim($request->query->getString('email')),
                'submitting' => false,
            ],
        ]);
    }

    #[Route('/design-system', name: 'app_design_system', methods: ['GET'])]
    public function designSystem(): Response
    {
        return $this->render('design_system/index.html.twig');
    }
}
