<?php

namespace App\Security;

use App\Entity\PersonalAccessToken;
use App\Entity\User;
use App\Repository\PersonalAccessTokenRepository;
use Doctrine\ORM\EntityManagerInterface;

final class PersonalAccessTokenService
{
    private const TOKEN_PREFIX = 'toastit_';

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly PersonalAccessTokenRepository $personalAccessTokenRepository,
    ) {
    }

    public function issue(User $user, string $name, ?\DateTimeImmutable $expiresAt): PersonalAccessTokenIssueResult
    {
        $selector = bin2hex(random_bytes(8));
        $secret = bin2hex(random_bytes(24));
        $plainTextToken = sprintf('%s%s_%s', self::TOKEN_PREFIX, $selector, $secret);

        $token = (new PersonalAccessToken())
            ->setUser($user)
            ->setName($name)
            ->setSelector($selector)
            ->setTokenHash(hash('sha256', $plainTextToken))
            ->setExpiresAt($expiresAt);

        $this->entityManager->persist($token);
        $this->entityManager->flush();

        return new PersonalAccessTokenIssueResult($token, $plainTextToken);
    }

    /**
     * @return list<PersonalAccessToken>
     */
    public function listOwnedByUser(User $user): array
    {
        return $this->personalAccessTokenRepository->findVisibleOwnedByUser($user);
    }

    public function findOwnedByUserAndId(User $user, int $tokenId): ?PersonalAccessToken
    {
        return $this->personalAccessTokenRepository->findOneOwnedByUser($user, $tokenId);
    }

    public function revoke(PersonalAccessToken $token): void
    {
        if (!$token->isRevoked()) {
            $token->setRevokedAt(new \DateTimeImmutable());
            $this->entityManager->flush();
        }
    }

    public function findActiveByPlainText(string $plainTextToken): ?PersonalAccessToken
    {
        $selector = $this->extractSelector($plainTextToken);

        if (null === $selector) {
            return null;
        }

        $now = new \DateTimeImmutable();

        foreach ($this->personalAccessTokenRepository->findActiveBySelector($selector, $now) as $candidate) {
            if (!hash_equals($candidate->getTokenHash(), hash('sha256', $plainTextToken))) {
                continue;
            }

            return $candidate;
        }

        return null;
    }

    public function markUsed(PersonalAccessToken $token): void
    {
        $token->setLastUsedAt(new \DateTimeImmutable());
        $this->entityManager->flush();
    }

    private function extractSelector(string $plainTextToken): ?string
    {
        $segments = explode('_', $plainTextToken);
        if (count($segments) < 2) {
            return null;
        }

        $selector = $segments[count($segments) - 2];
        $secret = $segments[count($segments) - 1];

        if (!preg_match('/^[a-f0-9]{16}$/', $selector)) {
            return null;
        }

        if (!preg_match('/^[a-f0-9]{48}$/', $secret)) {
            return null;
        }

        return $selector;
    }
}
