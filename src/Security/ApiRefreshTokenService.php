<?php

namespace App\Security;

use App\Entity\ApiRefreshToken;
use App\Entity\User;
use App\Repository\ApiRefreshTokenRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;

final class ApiRefreshTokenService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ApiRefreshTokenRepository $refreshTokenRepository,
        private readonly UserRepository $userRepository,
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

    public function validate(string $plainToken, \DateTimeImmutable $now): RefreshTokenResult
    {
        $refreshToken = $this->refreshTokenRepository->findActiveByHash(hash('sha256', $plainToken), $now);

        if (!$refreshToken instanceof ApiRefreshToken || $refreshToken->isExpired($now)) {
            return new RefreshTokenResult(null, 'invalid_refresh_token');
        }

        return new RefreshTokenResult($refreshToken, null);
    }

    public function markUsed(ApiRefreshToken $refreshToken, \DateTimeImmutable $now): void
    {
        $refreshToken->setLastUsedAt($now);
        $this->entityManager->flush();
    }

    public function getUserRepository(): UserRepository
    {
        return $this->userRepository;
    }
}
