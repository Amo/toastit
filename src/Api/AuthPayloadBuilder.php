<?php

namespace App\Api;

use App\Entity\User;

final class AuthPayloadBuilder
{
    /**
     * @return array{id: int|null, displayName: string, email: ?string, initials: string, gravatarUrl: string, isRoot: bool}
     */
    public function buildUser(User $user): array
    {
        return [
            'id' => $user->getId(),
            'displayName' => $user->getDisplayName(),
            'email' => $user->getPublicEmail(),
            'initials' => $user->getInitials(),
            'gravatarUrl' => $user->getGravatarUrl(),
            'isRoot' => $user->isRoot(),
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
