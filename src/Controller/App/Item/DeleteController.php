<?php

namespace App\Controller\App\Item;

use App\Workspace\WorkspaceAccess;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Routing\Attribute\Route;

final class DeleteController extends AbstractController
{
    public function __construct(
        private readonly WorkspaceAccess $workspaceAccess,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    #[Route('/app/items/{id}/delete', name: 'app_item_delete', methods: ['POST'])]
    public function __invoke(int $id): RedirectResponse
    {
        $item = $this->workspaceAccess->getItemOrFail($id);
        $workspace = $this->workspaceAccess->getWorkspaceOrFail($item->getWorkspace()->getId());
        $user = $this->workspaceAccess->getUserOrFail();

        if ($item->getAuthor()->getId() !== $user->getId()) {
            $this->addFlash('error', 'Seul l auteur du toast peut le supprimer.');

            return $this->redirectToRoute('app_workspace_show', ['id' => $workspace->getId()]);
        }

        $this->entityManager->remove($item);
        $this->entityManager->flush();
        $this->addFlash('success', 'Le toast a ete supprime.');

        return $this->redirectToRoute('app_workspace_show', ['id' => $workspace->getId()]);
    }
}
