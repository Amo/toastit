<?php

namespace App\Controller\App\Item;

use App\Entity\Toast;
use App\Workspace\WorkspaceAccess;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
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
        $workspace = $item->getWorkspace();
        $this->workspaceAccess->assertOwner($workspace);
        $this->workspaceAccess->assertMeetingModeIdle($workspace);

        if ($item->isToasted()) {
            throw new AccessDeniedHttpException();
        }

        if ($item->isVetoed()) {
            $item
                ->setStatus(Toast::STATUS_OPEN)
                ->setStatusChangedAt(null);
        } else {
            $item
                ->setStatus(Toast::STATUS_VETOED)
                ->setIsBoosted(false)
                ->setStatusChangedAt(new \DateTimeImmutable());
        }

        $this->entityManager->flush();

        if ($request->isXmlHttpRequest() || str_contains((string) $request->headers->get('Accept'), 'application/json')) {
            return new JsonResponse([
                'id' => $item->getId(),
                'vetoed' => $item->isVetoed(),
            ]);
        }

        return $this->redirectToRoute('app_workspace_show', ['id' => $workspace->getId()]);
    }
}
