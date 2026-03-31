<?php

namespace App\Controller\Api\Dashboard;

use App\Entity\Team;
use App\Entity\TeamMember;
use App\Workspace\WorkspaceAccess;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

final class CreateTeamController extends AbstractController
{
    public function __construct(
        private readonly WorkspaceAccess $workspaceAccess,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    #[Route('/api/teams', name: 'api_team_create', methods: ['POST'])]
    public function __invoke(Request $request): JsonResponse
    {
        $payload = $request->toArray();
        $name = trim((string) ($payload['name'] ?? ''));

        if ('' === $name) {
            return $this->json(['ok' => false, 'error' => 'invalid_name'], 400);
        }

        $user = $this->workspaceAccess->getUserOrFail();
        $team = (new Team())
            ->setName($name)
            ->setOrganizer($user);
        $membership = (new TeamMember())
            ->setUser($user);

        $team->addMembership($membership);
        $this->entityManager->persist($team);
        $this->entityManager->persist($membership);
        $this->entityManager->flush();

        return $this->json(['ok' => true, 'teamId' => $team->getId()]);
    }
}
