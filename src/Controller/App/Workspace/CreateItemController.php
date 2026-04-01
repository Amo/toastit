<?php

namespace App\Controller\App\Workspace;

use App\Entity\Toast;
use App\Workspace\WorkspaceAccess;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

final class CreateItemController extends AbstractController
{
    public function __construct(
        private readonly WorkspaceAccess $workspaceAccess,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    #[Route('/app/workspaces/{id}/items', name: 'app_workspace_item_create', methods: ['POST'])]
    public function __invoke(int $id, Request $request): RedirectResponse
    {
        $workspace = $this->workspaceAccess->getWorkspaceOrFail($id);
        $title = trim($request->request->getString('title'));

        if ('' === $title) {
            $this->addFlash('error', 'Le titre du toast est requis.');

            return $this->redirectToRoute('app_workspace_show', ['id' => $workspace->getId()]);
        }

        $item = (new Toast())
            ->setWorkspace($workspace)
            ->setAuthor($this->workspaceAccess->getUserOrFail())
            ->setTitle($title)
            ->setDescription(trim($request->request->getString('description')) ?: null);

        $this->entityManager->persist($item);
        $this->entityManager->flush();

        return $this->redirectToRoute('app_workspace_show', ['id' => $workspace->getId()]);
    }
}
