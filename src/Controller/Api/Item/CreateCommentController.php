<?php

namespace App\Controller\Api\Item;

use App\Entity\ToastComment;
use App\Workspace\WorkspaceAccessService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

final class CreateCommentController extends AbstractController
{
    public function __construct(
        private readonly WorkspaceAccessService $workspaceAccess,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    #[Route('/api/items/{id}/comments', name: 'api_item_comment_create', methods: ['POST'])]
    public function __invoke(int $id, Request $request): JsonResponse
    {
        $item = $this->workspaceAccess->getItemOrFail($id);

        if (!$item->isNew()) {
            return $this->json(['ok' => false, 'error' => 'comments_closed'], 400);
        }

        $payload = $request->toArray();
        $content = trim((string) ($payload['content'] ?? ''));

        if ('' === $content) {
            return $this->json(['ok' => false, 'error' => 'missing_content'], 400);
        }

        $comment = (new ToastComment())
            ->setToast($item)
            ->setAuthor($this->workspaceAccess->getUserOrFail())
            ->setContent($content);

        $item->addComment($comment);
        $this->entityManager->persist($comment);
        $this->entityManager->flush();

        return $this->json(['ok' => true]);
    }
}
