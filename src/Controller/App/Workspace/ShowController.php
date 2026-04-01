<?php

namespace App\Controller\App\Workspace;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class ShowController extends AbstractController
{
    #[Route('/app/workspaces/{id}', name: 'app_workspace_show', methods: ['POST'])]
    public function __invoke(int $id): Response
    {
        throw new \LogicException('GET handled by SPA shell.');
    }
}
