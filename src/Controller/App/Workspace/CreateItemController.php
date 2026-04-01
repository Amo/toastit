<?php

namespace App\Controller\App\Workspace;

use App\Entity\Toast;
use App\Workspace\WorkspaceAccess;
use App\Workspace\WorkspaceWorkflow;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

final class CreateItemController extends AbstractController
{
    public function __construct(
        private readonly WorkspaceAccess $workspaceAccess,
        private readonly WorkspaceWorkflow $workspaceWorkflow,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    #[Route('/app/workspaces/{id}/items', name: 'app_workspace_item_create', methods: ['POST'])]
    public function __invoke(int $id, Request $request): RedirectResponse
    {
        $workspace = $this->workspaceAccess->getWorkspaceOrFail($id);
        $title = trim($request->request->getString('title'));

        if ('' === $title) {
            $this->addFlash('error', 'Toast title is required.');

            return $this->redirectToRoute('app_workspace_show', ['id' => $workspace->getId()]);
        }

        $ownerIdRaw = trim($request->request->getString('owner_id'));
        $ownerId = ctype_digit($ownerIdRaw) ? (int) $ownerIdRaw : 0;
        $owner = $this->workspaceWorkflow->findWorkspaceInviteeById($workspace, $ownerId);
        $dueAtRaw = trim($request->request->getString('due_on'));
        $dueAt = null;

        if ('' !== $dueAtRaw) {
            try {
                $dueAt = new \DateTimeImmutable($dueAtRaw);
            } catch (\Exception) {
                $this->addFlash('error', 'The due date is invalid.');

                return $this->redirectToRoute('app_workspace_show', ['id' => $workspace->getId()]);
            }
        }

        $item = (new Toast())
            ->setWorkspace($workspace)
            ->setAuthor($this->workspaceAccess->getUserOrFail())
            ->setTitle($title)
            ->setDescription(trim($request->request->getString('description')) ?: null)
            ->setOwner($owner)
            ->setDueAt($dueAt);

        $this->entityManager->persist($item);
        $this->entityManager->flush();

        return $this->redirectToRoute('app_workspace_show', ['id' => $workspace->getId()]);
    }
}
