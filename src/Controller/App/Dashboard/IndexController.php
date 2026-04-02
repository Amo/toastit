<?php

namespace App\Controller\App\Dashboard;

use App\Entity\Workspace;
use App\Entity\WorkspaceMember;
use App\Workspace\WorkspaceAccessService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class IndexController extends AbstractController
{
    public function __construct(
        private readonly WorkspaceAccessService $workspaceAccess,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    #[Route('/app', name: 'app_dashboard', methods: ['POST'])]
    public function __invoke(Request $request): Response
    {
        $user = $this->workspaceAccess->getUserOrFail();

        if ('POST' === $request->getMethod()) {
            $name = trim($request->request->getString('name'));

            if ('' === $name) {
                $this->addFlash('error', 'Workspace name is required.');

                return $this->redirectToRoute('app_dashboard');
            }

            $workspace = (new Workspace())
                ->setName($name)
                ->setOrganizer($user);

            $membership = (new WorkspaceMember())
                ->setUser($user)
                ->setIsOwner(true);

            $workspace->addMembership($membership);
            $this->entityManager->persist($workspace);
            $this->entityManager->persist($membership);
            $this->entityManager->flush();

            return $this->redirectToRoute('app_workspace_show', ['id' => $workspace->getId()]);
        }

        throw new \LogicException('GET handled by SPA shell.');
    }
}
