<?php

namespace App\Security;

use App\Entity\ApiRefreshToken;
use App\Entity\User;
use App\Repository\ApiRefreshTokenRepository;
use Doctrine\ORM\EntityManagerInterface;

final class ApiRefreshTokenManager
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ApiRefreshTokenRepository $refreshTokenRepository,
    ) {
    }

    public function issue(User $user, \DateTimeImmutable $now): string
    {
        $plainToken = bin2hex(random_bytes(32));
        $refreshToken = (new ApiRefreshToken())
            ->setUser($user)
            ->setTokenHash(hash('sha256', $plainToken))
            ->setLastUsedAt($now)
            ->setExpiresAt($now->modify('+7 days'));

        $this->entityManager->persist($refreshToken);
        $this->entityManager->flush();

        return $plainToken;
    }

    public function validate(string $plainToken, \DateTimeImmutable $now): RefreshTokenValidation
    {
        $refreshToken = $this->refreshTokenRepository->findActiveByHash(hash('sha256', $plainToken), $now);

        if (!$refreshToken instanceof ApiRefreshToken || $refreshToken->isExpired($now)) {
            return new RefreshTokenValidation(null, 'invalid_refresh_token');
        }

        if ($refreshToken->isInactive($now)) {
            return new RefreshTokenValidation(null, 'refresh_inactive');
        }

        return new RefreshTokenValidation($refreshToken, null);
    }

    public function markUsed(ApiRefreshToken $refreshToken, \DateTimeImmutable $now): void
    {
        $refreshToken->setLastUsedAt($now);
        $this->entityManager->flush();
    }
}
