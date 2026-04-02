<?php

namespace App\Controller\App\Item;

use App\Workspace\WorkspaceAccessService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Routing\Attribute\Route;

final class DeleteController extends AbstractController
{
    public function __construct(
        private readonly WorkspaceAccessService $workspaceAccess,
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
            $this->addFlash('error', 'Only the toast author can delete it.');

            return $this->redirectToRoute('app_workspace_show', ['id' => $workspace->getId()]);
        }

        $this->entityManager->remove($item);
        $this->entityManager->flush();
        $this->addFlash('success', 'The toast was deleted.');

        return $this->redirectToRoute('app_workspace_show', ['id' => $workspace->getId()]);
    }
}
