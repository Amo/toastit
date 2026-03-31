<?php

namespace App\Controller\App\Item;

use App\Entity\ParkingLotItem;
use App\Workspace\WorkspaceAccess;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

final class ToggleVetoController extends AbstractController
{
    public function __construct(
        private readonly WorkspaceAccess $workspaceAccess,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    #[Route('/app/items/{id}/veto', name: 'app_item_veto_toggle', methods: ['POST'])]
    public function __invoke(int $id, Request $request): Response
    {
        $item = $this->workspaceAccess->getItemOrFail($id);
        $meeting = $item->getMeeting();
        $this->workspaceAccess->assertOrganizer($meeting);
        $this->workspaceAccess->assertMeetingEditable($meeting);

        if ($item->isVetoed()) {
            $item->setStatus(ParkingLotItem::STATUS_OPEN);
        } else {
            $item
                ->setStatus(ParkingLotItem::STATUS_VETOED)
                ->setIsBoosted(false);
        }

        $this->entityManager->flush();

        if ($request->isXmlHttpRequest() || str_contains((string) $request->headers->get('Accept'), 'application/json')) {
            return new JsonResponse([
                'id' => $item->getId(),
                'vetoed' => $item->isVetoed(),
            ]);
        }

        return $this->redirectToRoute('app_meeting_show', ['id' => $item->getMeeting()->getId()]);
    }
}
