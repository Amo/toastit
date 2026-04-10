<?php

namespace App\Controller\PublicApi\Toast;

use App\Entity\ToastComment;
use App\Workspace\WorkspaceAccessService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

final class CreateToastCommentController extends AbstractController
{
    public function __construct(
        private readonly WorkspaceAccessService $workspaceAccess,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    #[Route('/toasts/{id}/comments', name: 'public_api_toast_comment_create', methods: ['POST'])]
    public function __invoke(int $id, Request $request): JsonResponse
    {
        $toast = $this->workspaceAccess->getItemOrFail($id);

        if (!$toast->isNew()) {
            return $this->json(['ok' => false, 'error' => 'comments_closed'], 400);
        }

        $payload = $request->toArray();
        $content = trim((string) ($payload['content'] ?? ''));

        if ('' === $content) {
            return $this->json(['ok' => false, 'error' => 'missing_content'], 400);
        }

        $comment = (new ToastComment())
            ->setToast($toast)
            ->setAuthor($this->workspaceAccess->getUserOrFail())
            ->setContent($content);

        $toast->addComment($comment);
        $this->entityManager->persist($comment);
        $this->entityManager->flush();

        return $this->json([
            'ok' => true,
            'comment' => [
                'id' => $comment->getId(),
            ],
        ], 201);
    }
}
