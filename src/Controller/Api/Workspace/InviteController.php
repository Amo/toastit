<?php

namespace App\Controller\Api\Workspace;

use App\Entity\WorkspaceMember;
use App\Repository\WorkspaceMemberRepository;
use App\Workspace\WorkspaceUserService;
use App\Workspace\WorkspaceAccessService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

final class InviteController extends AbstractController
{
    public function __construct(
        private readonly WorkspaceAccessService $workspaceAccess,
        private readonly WorkspaceUserService $userProvisioner,
        private readonly WorkspaceMemberRepository $workspaceMemberRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    #[Route('/api/workspaces/{id}/invite', name: 'api_workspace_invite', methods: ['POST'])]
    public function __invoke(int $id, Request $request): JsonResponse
    {
        $workspace = $this->workspaceAccess->getWorkspaceOrFail($id);
        $this->workspaceAccess->assertOwner($workspace);
        $payload = $request->toArray();
        $email = trim((string) ($payload['email'] ?? ''));

        if ('' === $email) {
            return $this->json(['ok' => false, 'error' => 'missing_email'], 400);
        }

        $user = $this->userProvisioner->findOrCreateUserByEmail($email);

        foreach ($workspace->getMemberships() as $membership) {
            if ($membership->getUser()->getId() === $user->getId()) {
                return $this->json(['ok' => true, 'alreadyMember' => true]);
            }
        }

        $membership = (new WorkspaceMember())
            ->setWorkspace($workspace)
            ->setDisplayOrder($this->workspaceMemberRepository->nextDisplayOrderForUser($user))
            ->setUser($user);

        $workspace->addMembership($membership);
        $this->entityManager->persist($membership);
        $this->entityManager->flush();

        return $this->json(['ok' => true]);
    }
}
