<?php

namespace App\Controller\App\Meeting;

use App\Entity\Meeting;
use App\Workspace\WorkspaceAccess;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Routing\Attribute\Route;

final class StartController extends AbstractController
{
    public function __construct(
        private readonly WorkspaceAccess $workspaceAccess,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    #[Route('/app/meetings/{id}/start', name: 'app_meeting_start', methods: ['POST'])]
    public function __invoke(int $id): RedirectResponse
    {
        $meeting = $this->workspaceAccess->getMeetingOrFail($id);
        $this->workspaceAccess->assertOrganizer($meeting);

        if (!$meeting->isClosed()) {
            $meeting
                ->setStatus(Meeting::STATUS_LIVE)
                ->setStartedAt($meeting->getStartedAt() ?? new \DateTimeImmutable())
                ->setClosedAt(null);

            $this->entityManager->flush();
            $this->addFlash('success', 'Le meeting a demarre.');
        }

        return $this->redirectToRoute('app_meeting_show', ['id' => $meeting->getId()]);
    }
}
