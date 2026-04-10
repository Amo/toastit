<?php

namespace App\Controller\PublicApi\Toast;

use App\Entity\Vote;
use App\Repository\VoteRepository;
use App\Workspace\WorkspaceAccessService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

final class SetToastVoteController extends AbstractController
{
    public function __construct(
        private readonly WorkspaceAccessService $workspaceAccess,
        private readonly VoteRepository $voteRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    #[Route('/toasts/{id}/vote', name: 'public_api_toast_vote_set', methods: ['PUT'])]
    public function __invoke(int $id, Request $request): JsonResponse
    {
        $user = $this->workspaceAccess->getUserOrFail();
        $toast = $this->workspaceAccess->getItemOrFail($id);
        $this->workspaceAccess->assertMeetingModeIdle($toast->getWorkspace());

        if (!$toast->isNew()) {
            return $this->json(['ok' => false, 'error' => 'vote_not_allowed'], 400);
        }

        $payload = $request->toArray();
        if (!is_bool($payload['voted'] ?? null)) {
            return $this->json(['ok' => false, 'error' => 'invalid_voted_flag'], 400);
        }

        $existingVote = $this->voteRepository->findOneForItemAndUser($toast, $user);
        $shouldVote = true === $payload['voted'];

        if ($existingVote instanceof Vote && !$shouldVote) {
            $this->entityManager->remove($existingVote);
            $toast->removeVote($existingVote);
        } elseif (!$existingVote instanceof Vote && $shouldVote) {
            $vote = (new Vote())
                ->setItem($toast)
                ->setUser($user);

            $this->entityManager->persist($vote);
            $toast->addVote($vote);
        }

        $this->entityManager->flush();

        return $this->json([
            'ok' => true,
            'toast' => [
                'id' => $toast->getId(),
                'voted' => null !== $this->voteRepository->findOneForItemAndUser($toast, $user),
                'voteCount' => $toast->getVoteCount(),
            ],
        ]);
    }
}
