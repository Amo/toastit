<?php

namespace App\Api;

use App\Entity\User;
use App\Profile\AvatarUrlService;
use App\Repository\WorkspaceRepository;
use App\Workspace\InboundEmailAddressService;

final class AuthPayloadBuilder
{
    public function __construct(
        private readonly AvatarUrlService $avatarUrl,
        private readonly WorkspaceRepository $workspaceRepository,
        private readonly InboundEmailAddressService $inboundEmailAddress,
    ) {
    }

    /**
     * @return array{id: int|null, displayName: string, email: ?string, initials: string, gravatarUrl: string, isRoot: bool, isRoute: bool, advancedAiModelEnabled: bool, inboxWorkspaceId: int|null, inboxEmailAddress: string|null}
     */
    public function buildUser(User $user): array
    {
        $inboxWorkspace = $this->workspaceRepository->findInboxWorkspaceForUser($user);

        return [
            'id' => $user->getId(),
            'displayName' => $user->getDisplayName(),
            'email' => $user->getPublicEmail(),
            'initials' => $user->getInitials(),
            'gravatarUrl' => $this->avatarUrl->resolve($user),
            'isRoot' => $user->isRoot(),
            'isRoute' => $user->isRoute(),
            'advancedAiModelEnabled' => $user->isAdvancedAiModelEnabled(),
            'inboxWorkspaceId' => $inboxWorkspace?->getId(),
            'inboxEmailAddress' => $this->inboundEmailAddress->buildAddressForUser($user),
        ];
    }

    /**
     * @return array{ok: true, accessToken: string, refreshToken: string, user: array<string, mixed>, pinLockExpiresAt: int}
     */
    public function buildAuthenticated(User $user, string $accessToken, string $refreshToken, int $pinLockExpiresAt): array
    {
        return [
            'ok' => true,
            'accessToken' => $accessToken,
            'refreshToken' => $refreshToken,
            'user' => $this->buildUser($user),
            'pinLockExpiresAt' => $pinLockExpiresAt,
        ];
    }
}
