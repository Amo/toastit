<?php

namespace App\Controller\PublicApi\Toast;

use App\Workspace\ToastCreationService;
use App\Workspace\WorkspaceAccessService;
use App\Workspace\WorkspaceWorkflowService;
use App\Entity\Workspace;
use App\Entity\Toast;
use App\Entity\ToastComment;
use App\PublicApi\PublicApiVersionService;
use App\Repository\ToastCommentRepository;
use App\Repository\ToastRepository;
use App\Repository\WorkspaceRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ParameterBag;
use Symfony\Component\Routing\Attribute\Route;

final class CreateToastController extends AbstractController
{
    public function __construct(
        private readonly WorkspaceAccessService $workspaceAccess,
        private readonly WorkspaceWorkflowService $workspaceWorkflow,
        private readonly WorkspaceRepository $workspaceRepository,
        private readonly ToastRepository $toastRepository,
        private readonly ToastCommentRepository $toastCommentRepository,
        private readonly ToastCreationService $toastCreation,
        private readonly EntityManagerInterface $entityManager,
        private readonly PublicApiVersionService $publicApiVersionService,
    ) {
    }

    #[Route('/doc', name: 'public_api_doc', methods: ['GET'])]
    public function doc(): Response
    {
        return $this->render('public_api/doc.html.twig', [
            'expected_accept' => $this->publicApiVersionService->buildExpectedAcceptValue(),
        ]);
    }

    #[Route('/workspaces', name: 'public_api_workspace_list', methods: ['GET'])]
    public function listWorkspaces(): JsonResponse
    {
        $currentUser = $this->workspaceAccess->getUserOrFail();

        return $this->json([
            'ok' => true,
            'workspaces' => array_map(
                fn (Workspace $workspace): array => [
                    'id' => $workspace->getId(),
                    'name' => $workspace->getName(),
                    'isDefault' => $workspace->isDefault(),
                    'isSoloWorkspace' => $workspace->isSoloWorkspace(),
                    'meetingMode' => $workspace->getMeetingMode(),
                    'memberCount' => $workspace->getMemberships()->count(),
                    'currentUserIsOwner' => $workspace->isOwnedBy($currentUser),
                    'createdAt' => $workspace->getCreatedAt()->format(\DateTimeInterface::ATOM),
                ],
                $this->workspaceRepository->findForUser($currentUser)
            ),
        ]);
    }

    #[Route('/workspaces/{id}/toasts', name: 'public_api_workspace_toast_list', methods: ['GET'])]
    public function listWorkspaceToasts(int $id, Request $request): JsonResponse
    {
        $workspace = $this->workspaceAccess->getWorkspaceOrFail($id);
        $status = strtolower(trim((string) $request->query->get('status', ToastRepository::PUBLIC_STATUS_ALL)));
        $page = max(1, (int) $request->query->get('page', 1));
        $perPage = min(100, max(1, (int) $request->query->get('perPage', 20)));

        if (!$this->toastRepository->isSupportedPublicStatus($status)) {
            return $this->json([
                'ok' => false,
                'error' => 'invalid_status',
                'supportedStatuses' => [
                    ToastRepository::PUBLIC_STATUS_ALL,
                    ToastRepository::PUBLIC_STATUS_NEW,
                    ToastRepository::PUBLIC_STATUS_READY,
                    ToastRepository::PUBLIC_STATUS_TOASTED,
                    ToastRepository::PUBLIC_STATUS_DISCARDED,
                ],
            ], 400);
        }

        $result = $this->toastRepository->findPaginatedForWorkspace($workspace, $status, $page, $perPage);
        $toasts = $result['toasts'];
        $total = $result['total'];

        return $this->json([
            'ok' => true,
            'toasts' => array_map(
                static fn (Toast $toast): array => [
                    'id' => $toast->getId(),
                    'title' => $toast->getTitle(),
                    'description' => $toast->getDescription(),
                    'status' => $toast->getStatus(),
                    'publicStatus' => self::resolvePublicStatus($toast),
                    'isBoosted' => $toast->isBoosted(),
                    'dueOn' => $toast->getDueAt()?->format('Y-m-d'),
                    'createdAt' => $toast->getCreatedAt()->format(\DateTimeInterface::ATOM),
                    'assigneeEmail' => $toast->getOwner()?->getPublicEmail(),
                    'author' => [
                        'id' => $toast->getAuthor()->getId(),
                        'displayName' => $toast->getAuthor()->getDisplayName(),
                        'email' => $toast->getAuthor()->getPublicEmail(),
                    ],
                    'voteCount' => $toast->getVoteCount(),
                ],
                $toasts
            ),
            'pagination' => [
                'page' => $page,
                'perPage' => $perPage,
                'total' => $total,
                'totalPages' => max(1, (int) ceil($total / $perPage)),
                'nextPageUrl' => $this->buildNextPageUrl($request->query, $request, $page, $perPage, $total),
            ],
        ]);
    }

    #[Route('/workspaces/{id}/toasts', name: 'public_api_toast_create', methods: ['POST'])]
    public function __invoke(int $id, Request $request): JsonResponse
    {
        $workspace = $this->workspaceAccess->getWorkspaceOrFail($id);
        $payload = $request->toArray();
        $title = trim((string) ($payload['title'] ?? ''));

        if ('' === $title) {
            return $this->json(['ok' => false, 'error' => 'missing_title'], 400);
        }

        $ownerEmail = trim((string) ($payload['assigneeEmail'] ?? ''));
        $owner = $this->workspaceWorkflow->findWorkspaceInviteeByEmail($workspace, $ownerEmail);

        if ('' !== $ownerEmail && null === $owner) {
            return $this->json(['ok' => false, 'error' => 'unknown_assignee'], 400);
        }

        $dueAt = null;

        if (array_key_exists('dueOn', $payload) && null !== $payload['dueOn'] && '' !== trim((string) $payload['dueOn'])) {
            try {
                $dueAt = new \DateTimeImmutable((string) $payload['dueOn']);
            } catch (\Exception) {
                return $this->json(['ok' => false, 'error' => 'invalid_due_on'], 400);
            }
        }

        $toast = $this->toastCreation->createToast(
            $workspace,
            $this->workspaceAccess->getUserOrFail(),
            $title,
            trim((string) ($payload['description'] ?? '')) ?: null,
            $owner,
            $dueAt,
        );

        $this->entityManager->flush();

        return $this->json([
            'ok' => true,
            'toast' => [
                'id' => $toast->getId(),
            ],
        ], 201);
    }

    #[Route('/toasts/{id}/comments', name: 'public_api_toast_comment_list', methods: ['GET'])]
    public function listToastComments(int $id, Request $request): JsonResponse
    {
        $toast = $this->workspaceAccess->getItemOrFail($id);
        $page = max(1, (int) $request->query->get('page', 1));
        $perPage = min(100, max(1, (int) $request->query->get('perPage', 20)));

        $result = $this->toastCommentRepository->findPaginatedForToast($toast, $page, $perPage);
        $comments = $result['comments'];
        $total = $result['total'];

        return $this->json([
            'ok' => true,
            'comments' => array_map(
                static fn (ToastComment $comment): array => [
                    'id' => $comment->getId(),
                    'content' => $comment->getContent(),
                    'createdAt' => $comment->getCreatedAt()->format(\DateTimeInterface::ATOM),
                    'author' => [
                        'id' => $comment->getAuthor()->getId(),
                        'displayName' => $comment->getAuthor()->getDisplayName(),
                        'email' => $comment->getAuthor()->getPublicEmail(),
                    ],
                ],
                $comments
            ),
            'pagination' => [
                'page' => $page,
                'perPage' => $perPage,
                'total' => $total,
                'totalPages' => max(1, (int) ceil($total / $perPage)),
                'nextPageUrl' => $this->buildNextPageUrl($request->query, $request, $page, $perPage, $total),
            ],
        ]);
    }

    private static function resolvePublicStatus(Toast $toast): string
    {
        if ($toast->isVetoed()) {
            return ToastRepository::PUBLIC_STATUS_DISCARDED;
        }

        if ($toast->isToasted()) {
            return ToastRepository::PUBLIC_STATUS_TOASTED;
        }

        if ($toast->isReady()) {
            return ToastRepository::PUBLIC_STATUS_READY;
        }

        return ToastRepository::PUBLIC_STATUS_NEW;
    }

    private function buildNextPageUrl(ParameterBag $query, Request $request, int $page, int $perPage, int $total): ?string
    {
        if (($page * $perPage) >= $total) {
            return null;
        }

        $nextQuery = $query->all();
        $nextQuery['page'] = $page + 1;
        $nextQuery['perPage'] = $perPage;

        return $request->getSchemeAndHttpHost().$request->getPathInfo().'?'.http_build_query($nextQuery);
    }
}
