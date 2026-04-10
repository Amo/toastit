<?php

namespace App\Controller\PublicApi\Workspace;

use App\Entity\WorkspaceMember;
use App\Repository\WorkspaceMemberRepository;
use App\Workspace\WorkspaceAccessService;
use App\Workspace\WorkspaceUserService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

final class ManageWorkspaceController extends AbstractController
{
    public function __construct(
        private readonly WorkspaceAccessService $workspaceAccess,
        private readonly WorkspaceUserService $workspaceUser,
        private readonly WorkspaceMemberRepository $workspaceMemberRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    #[Route('/workspaces/{id}/members', name: 'public_api_workspace_member_list', methods: ['GET'])]
    public function listMembers(int $id): JsonResponse
    {
        $workspace = $this->workspaceAccess->getWorkspaceOrFail($id);

        return $this->json([
            'ok' => true,
            'members' => array_map(
                static fn (WorkspaceMember $membership): array => [
                    'memberId' => $membership->getId(),
                    'userId' => $membership->getUser()->getId(),
                    'displayName' => $membership->getUser()->getDisplayName(),
                    'email' => $membership->getUser()->getPublicEmail(),
                    'isOwner' => $membership->isOwner(),
                ],
                $workspace->getMemberships()->toArray()
            ),
        ]);
    }

    #[Route('/workspaces/{id}/members', name: 'public_api_workspace_member_invite', methods: ['POST'])]
    public function inviteMember(int $id, Request $request): JsonResponse
    {
        $workspace = $this->workspaceAccess->getWorkspaceOrFail($id);
        $this->workspaceAccess->assertOwner($workspace);

        if ($workspace->isInboxWorkspace()) {
            return $this->json(['ok' => false, 'error' => 'inbox_workspace_not_shareable'], 400);
        }

        $payload = $request->toArray();
        $email = trim((string) ($payload['email'] ?? ''));

        if ('' === $email) {
            return $this->json(['ok' => false, 'error' => 'missing_email'], 400);
        }

        $user = $this->workspaceUser->findOrCreateUserByEmail($email);

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

        return $this->json([
            'ok' => true,
            'member' => [
                'memberId' => $membership->getId(),
                'userId' => $membership->getUser()->getId(),
                'displayName' => $membership->getUser()->getDisplayName(),
                'email' => $membership->getUser()->getPublicEmail(),
                'isOwner' => $membership->isOwner(),
            ],
        ], 201);
    }

    #[Route('/workspaces/{workspaceId}/members/{memberId}', name: 'public_api_workspace_member_remove', methods: ['DELETE'])]
    public function removeMember(int $workspaceId, int $memberId): JsonResponse
    {
        $workspace = $this->workspaceAccess->getWorkspaceOrFail($workspaceId);
        $this->workspaceAccess->assertOwner($workspace);
        $membership = $this->entityManager->getRepository(WorkspaceMember::class)->find($memberId);

        if (!$membership instanceof WorkspaceMember || $membership->getWorkspace()->getId() !== $workspace->getId()) {
            return $this->json(['ok' => false, 'error' => 'member_not_found'], 404);
        }

        if ($membership->isOwner() && $workspace->getOwnerCount() <= 1) {
            return $this->json(['ok' => false, 'error' => 'last_owner'], 400);
        }

        $this->entityManager->remove($membership);
        $this->entityManager->flush();

        return $this->json(['ok' => true]);
    }

    #[Route('/workspaces/{id}/name', name: 'public_api_workspace_name_update', methods: ['PATCH'])]
    public function updateName(int $id, Request $request): JsonResponse
    {
        $workspace = $this->workspaceAccess->getWorkspaceOrFail($id);
        $this->workspaceAccess->assertOwner($workspace);

        if ($workspace->isInboxWorkspace()) {
            return $this->json(['ok' => false, 'error' => 'inbox_workspace_not_configurable'], 400);
        }

        $payload = $request->toArray();
        $name = trim((string) ($payload['name'] ?? ''));

        if ('' === $name) {
            return $this->json(['ok' => false, 'error' => 'missing_name'], 400);
        }

        $workspace->setName($name);
        $this->entityManager->flush();

        return $this->json([
            'ok' => true,
            'workspace' => [
                'id' => $workspace->getId(),
                'name' => $workspace->getName(),
            ],
        ]);
    }

    #[Route('/workspaces/{id}', name: 'public_api_workspace_delete', methods: ['DELETE'])]
    public function deleteWorkspace(int $id): JsonResponse
    {
        $workspace = $this->workspaceAccess->getWorkspaceOrFail($id);
        $this->workspaceAccess->assertOwner($workspace);

        if ($workspace->isInboxWorkspace()) {
            return $this->json(['ok' => false, 'error' => 'inbox_workspace_not_deletable'], 400);
        }

        $workspace->softDelete();
        $this->entityManager->flush();

        return $this->json(['ok' => true]);
    }
}
