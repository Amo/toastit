<?php

namespace App\Controller\App\Workspace;

use App\Entity\WorkspaceMember;
use App\Workspace\UserProvisioner;
use App\Workspace\WorkspaceAccess;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

final class InviteController extends AbstractController
{
    public function __construct(
        private readonly WorkspaceAccess $workspaceAccess,
        private readonly UserProvisioner $userProvisioner,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    #[Route('/app/workspaces/{id}/invite', name: 'app_workspace_invite', methods: ['POST'])]
    public function __invoke(int $id, Request $request): RedirectResponse
    {
        $workspace = $this->workspaceAccess->getWorkspaceOrFail($id);
        $this->workspaceAccess->assertOwner($workspace);
        $email = trim($request->request->getString('email'));

        if ('' === $email) {
            $this->addFlash('error', 'Email is required.');

            return $this->redirectToRoute('app_workspace_show', ['id' => $workspace->getId()]);
        }

        $user = $this->userProvisioner->findOrCreateUserByEmail($email);

        foreach ($workspace->getMemberships() as $membership) {
            if ($membership->getUser()->getId() === $user->getId()) {
                return $this->redirectToRoute('app_workspace_show', ['id' => $workspace->getId()]);
            }
        }

        $membership = (new WorkspaceMember())
            ->setWorkspace($workspace)
            ->setUser($user);

        $workspace->addMembership($membership);
        $this->entityManager->persist($membership);
        $this->entityManager->flush();

        return $this->redirectToRoute('app_workspace_show', ['id' => $workspace->getId()]);
    }
}
