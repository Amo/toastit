<?php

namespace App\Controller\Api\Item;

use App\Entity\Vote;
use App\Repository\VoteRepository;
use App\Workspace\WorkspaceAccess;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

final class ToggleVoteController extends AbstractController
{
    public function __construct(
        private readonly WorkspaceAccess $workspaceAccess,
        private readonly VoteRepository $voteRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    #[Route('/api/items/{id}/vote', name: 'api_item_vote_toggle', methods: ['POST'])]
    public function __invoke(int $id): JsonResponse
    {
        $user = $this->workspaceAccess->getUserOrFail();
        $item = $this->workspaceAccess->getItemOrFail($id);
        $this->workspaceAccess->assertMeetingEditable($item->getMeeting());

        $existingVote = $this->voteRepository->findOneForItemAndUser($item, $user);
        $voted = true;

        if ($existingVote instanceof Vote) {
            $this->entityManager->remove($existingVote);
            $item->removeVote($existingVote);
            $voted = false;
        } else {
            $vote = (new Vote())
                ->setItem($item)
                ->setUser($user);

            $this->entityManager->persist($vote);
            $item->addVote($vote);
        }

        $this->entityManager->flush();

        return $this->json([
            'ok' => true,
            'id' => $item->getId(),
            'voted' => $voted,
            'voteCount' => $item->getVoteCount(),
        ]);
    }
}
