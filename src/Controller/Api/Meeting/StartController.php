<?php

namespace App\Controller\Api\Meeting;

use App\Entity\Meeting;
use App\Workspace\WorkspaceAccess;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

final class StartController extends AbstractController
{
    public function __construct(
        private readonly WorkspaceAccess $workspaceAccess,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    #[Route('/api/meetings/{id}/start', name: 'api_meeting_start', methods: ['POST'])]
    public function __invoke(int $id): JsonResponse
    {
        $meeting = $this->workspaceAccess->getMeetingOrFail($id);
        $this->workspaceAccess->assertOrganizer($meeting);

        if (!$meeting->isClosed()) {
            $meeting
                ->setStatus(Meeting::STATUS_LIVE)
                ->setStartedAt($meeting->getStartedAt() ?? new \DateTimeImmutable())
                ->setClosedAt(null);

            $this->entityManager->flush();
        }

        return $this->json(['ok' => true]);
    }
}
