<?php

namespace App\Controller\App\Item;

use App\Entity\Vote;
use App\Repository\VoteRepository;
use App\Workspace\WorkspaceAccess;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
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

    #[Route('/app/items/{id}/vote', name: 'app_item_vote_toggle', methods: ['POST'])]
    public function __invoke(int $id, Request $request): Response
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

        if ($request->isXmlHttpRequest() || str_contains((string) $request->headers->get('Accept'), 'application/json')) {
            return new JsonResponse([
                'id' => $item->getId(),
                'voted' => $voted,
                'voteCount' => $item->getVoteCount(),
            ]);
        }

        return $this->redirectToRoute('app_meeting_show', ['id' => $item->getMeeting()->getId()]);
    }
}
