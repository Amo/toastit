<?php

namespace App\Controller\App\Item;

use App\Entity\Meeting;
use App\Entity\ParkingLotItem;
use App\Workspace\WorkspaceAccess;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

final class RelocateController extends AbstractController
{
    public function __construct(
        private readonly WorkspaceAccess $workspaceAccess,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    #[Route('/app/items/{id}/relocate', name: 'app_item_relocate', methods: ['POST'])]
    public function __invoke(int $id, Request $request): RedirectResponse
    {
        $item = $this->entityManager->getRepository(ParkingLotItem::class)->find($id);

        if (!$item instanceof ParkingLotItem) {
            throw $this->createNotFoundException();
        }

        $currentMeeting = $this->workspaceAccess->getMeetingOrFail($item->getMeeting()->getId());
        $targetMeetingId = $request->request->getInt('target_meeting_id');
        $mode = $request->request->getString('mode');

        $targetMeeting = $this->entityManager->getRepository(Meeting::class)->find($targetMeetingId);

        if (!$targetMeeting instanceof Meeting || $targetMeeting->getTeam()->getId() !== $currentMeeting->getTeam()->getId()) {
            $this->addFlash('error', 'Le meeting cible est invalide.');

            return $this->redirectToRoute('app_meeting_show', ['id' => $currentMeeting->getId()]);
        }

        if ($targetMeeting->getId() === $currentMeeting->getId()) {
            $this->addFlash('error', 'Choisissez un autre meeting cible.');

            return $this->redirectToRoute('app_meeting_show', ['id' => $currentMeeting->getId()]);
        }

        if ('copy' === $mode) {
            $copy = (new ParkingLotItem())
                ->setTeam($item->getTeam())
                ->setMeeting($targetMeeting)
                ->setAuthor($item->getAuthor())
                ->setTitle($item->getTitle())
                ->setDescription($item->getDescription())
                ->setStatus(ParkingLotItem::STATUS_OPEN)
                ->setIsBoosted(false)
                ->setDiscussionStatus(ParkingLotItem::DISCUSSION_PENDING)
                ->setDiscussionNotes(null)
                ->setFollowUp(null)
                ->setOwner(null)
                ->setDueAt(null);

            $this->entityManager->persist($copy);
            $this->entityManager->flush();
            $this->addFlash('success', sprintf('Le sujet "%s" a ete copie vers "%s".', $item->getTitle(), $targetMeeting->getTitle()));

            return $this->redirectToRoute('app_meeting_show', ['id' => $targetMeeting->getId()]);
        }

        if ('move' !== $mode) {
            $this->addFlash('error', 'Action de deplacement invalide.');

            return $this->redirectToRoute('app_meeting_show', ['id' => $currentMeeting->getId()]);
        }

        $item
            ->setMeeting($targetMeeting)
            ->setStatus(ParkingLotItem::STATUS_OPEN)
            ->setIsBoosted(false)
            ->setDiscussionStatus(ParkingLotItem::DISCUSSION_PENDING)
            ->setDiscussionNotes(null)
            ->setFollowUp(null)
            ->setOwner(null)
            ->setDueAt(null);
        $this->entityManager->flush();
        $this->addFlash('success', sprintf('Le sujet "%s" a ete transfere vers "%s".', $item->getTitle(), $targetMeeting->getTitle()));

        return $this->redirectToRoute('app_meeting_show', ['id' => $targetMeeting->getId()]);
    }
}
