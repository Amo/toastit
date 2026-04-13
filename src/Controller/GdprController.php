<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class GdprController extends AbstractController
{
    #[Route('/gdpr', name: 'gdpr_page', methods: ['GET'])]
    public function __invoke(): Response
    {
        return $this->render('legal/gdpr.html.twig');
    }
}

