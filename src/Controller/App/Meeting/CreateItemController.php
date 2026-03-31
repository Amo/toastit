<?php

namespace App\Controller\App\Meeting;

use App\Entity\ParkingLotItem;
use App\Workspace\WorkspaceAccess;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

final class CreateItemController extends AbstractController
{
    public function __construct(
        private readonly WorkspaceAccess $workspaceAccess,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    #[Route('/app/meetings/{id}/items', name: 'app_meeting_item_create', methods: ['POST'])]
    public function __invoke(int $id, Request $request): RedirectResponse
    {
        $meeting = $this->workspaceAccess->getMeetingOrFail($id);
        $this->workspaceAccess->assertMeetingEditable($meeting);
        $title = trim($request->request->getString('title'));

        if ('' === $title) {
            $this->addFlash('error', 'Le titre du sujet est requis.');

            return $this->redirectToRoute('app_meeting_show', ['id' => $meeting->getId()]);
        }

        $item = (new ParkingLotItem())
            ->setTeam($meeting->getTeam())
            ->setMeeting($meeting)
            ->setAuthor($this->workspaceAccess->getUserOrFail())
            ->setTitle($title)
            ->setDescription(trim($request->request->getString('description')) ?: null);

        $this->entityManager->persist($item);
        $this->entityManager->flush();

        return $this->redirectToRoute('app_meeting_show', ['id' => $meeting->getId()]);
    }
}
