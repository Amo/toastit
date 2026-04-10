<?php

namespace App\Controller\PublicApi\Toast;

use App\Entity\Toast;
use App\Workspace\WorkspaceAccessService;
use App\Workspace\WorkspaceWorkflowService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Routing\Attribute\Route;

final class SetToastBoostController extends AbstractController
{
    public function __construct(
        private readonly WorkspaceAccessService $workspaceAccess,
        private readonly WorkspaceWorkflowService $workspaceWorkflow,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    #[Route('/toasts/{id}/boost', name: 'public_api_toast_boost_set', methods: ['PUT'])]
    public function __invoke(int $id, Request $request): JsonResponse
    {
        $toast = $this->workspaceAccess->getItemOrFail($id);
        $workspace = $toast->getWorkspace();
        $this->workspaceAccess->assertOwner($workspace);
        $this->workspaceAccess->assertMeetingModeIdle($workspace);

        if ($toast->isToasted()) {
            throw new AccessDeniedHttpException();
        }

        $payload = $request->toArray();
        if (!is_bool($payload['boosted'] ?? null)) {
            return $this->json(['ok' => false, 'error' => 'invalid_boosted_flag'], 400);
        }

        if (true === $payload['boosted']) {
            if ($toast->isVetoed()) {
                $toast->setStatus(Toast::STATUS_PENDING);
            }

            $toast
                ->setIsBoosted(true)
                ->setBoostRank($this->workspaceWorkflow->nextBoostRank($workspace));
        } else {
            $toast->setIsBoosted(false);
        }

        $this->entityManager->flush();

        return $this->json([
            'ok' => true,
            'toast' => [
                'id' => $toast->getId(),
                'boosted' => $toast->isBoosted(),
                'boostRank' => $toast->getBoostRank(),
            ],
        ]);
    }
}
