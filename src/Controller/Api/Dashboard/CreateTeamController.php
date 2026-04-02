<?php

namespace App\Controller\Api\Dashboard;

use App\Entity\Workspace;
use App\Entity\WorkspaceMember;
use App\Repository\WorkspaceMemberRepository;
use App\Workspace\WorkspaceAccessService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

final class CreateTeamController extends AbstractController
{
    public function __construct(
        private readonly WorkspaceAccessService $workspaceAccess,
        private readonly WorkspaceMemberRepository $workspaceMemberRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    #[Route('/api/workspaces', name: 'api_workspace_create', methods: ['POST'])]
    public function __invoke(Request $request): JsonResponse
    {
        $payload = $request->toArray();
        $name = trim((string) ($payload['name'] ?? ''));

        if ('' === $name) {
            return $this->json(['ok' => false, 'error' => 'invalid_name'], 400);
        }

        $user = $this->workspaceAccess->getUserOrFail();
        $workspace = (new Workspace())
            ->setName($name)
            ->setOrganizer($user);
        $membership = (new WorkspaceMember())
            ->setUser($user)
            ->setDisplayOrder($this->workspaceMemberRepository->nextDisplayOrderForUser($user))
            ->setIsOwner(true);

        $workspace->addMembership($membership);
        $this->entityManager->persist($workspace);
        $this->entityManager->persist($membership);
        $this->entityManager->flush();

        return $this->json(['ok' => true, 'workspaceId' => $workspace->getId()]);
    }
}
