<?php

namespace App\Controller\Api\Admin;

use App\Api\AuthPayloadBuilder;
use App\Repository\UserRepository;
use App\Security\ApiRefreshTokenService;
use App\Security\JwtTokenService;
use App\Workspace\WorkspaceAccessService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

final class UserImpersonateController extends AbstractController
{
    public function __construct(
        private readonly WorkspaceAccessService $workspaceAccess,
        private readonly UserRepository $userRepository,
        private readonly ApiRefreshTokenService $refreshTokenService,
        private readonly JwtTokenService $jwtTokenService,
        private readonly AuthPayloadBuilder $authPayloadBuilder,
    ) {
    }

    #[Route('/api/admin/users/{id<\d+>}/impersonate', name: 'api_admin_user_impersonate', methods: ['POST'])]
    public function __invoke(int $id): JsonResponse
    {
        $actor = $this->workspaceAccess->getUserOrFail();
        $this->workspaceAccess->assertRouteOrRoot();

        $target = $this->userRepository->find($id);
        if (null === $target || $target->isDeleted()) {
            return $this->json([
                'ok' => false,
                'error' => 'user_not_found',
            ], 404);
        }

        if ($target->getId() === $actor->getId()) {
            return $this->json([
                'ok' => false,
                'error' => 'cannot_impersonate_self',
            ], 400);
        }

        $now = new \DateTimeImmutable();
        $refreshToken = $this->refreshTokenService->issue($target, $now);

        return $this->json($this->authPayloadBuilder->buildAuthenticated(
            $target,
            $this->jwtTokenService->createAccessToken($target, $now),
            $refreshToken,
            $now->getTimestamp() + 900,
        ));
    }
}
