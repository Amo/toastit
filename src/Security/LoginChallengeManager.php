<?php

namespace App\Security;

use App\Entity\LoginChallenge;
use App\Entity\User;
use App\Repository\LoginChallengeRepository;
use App\Repository\UserRepository;
use App\Workspace\UserProvisioner;
use Doctrine\ORM\EntityManagerInterface;

final class LoginChallengeManager
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UserRepository $userRepository,
        private readonly LoginChallengeRepository $challengeRepository,
        private readonly EmailNormalizer $emailNormalizer,
        private readonly ChallengeFactory $challengeFactory,
        private readonly UserProvisioner $userProvisioner,
    ) {
    }

    public function getOrCreateUser(string $email): User
    {
        $normalizedEmail = $this->emailNormalizer->normalize($email);
        $user = $this->userRepository->findOneByNormalizedEmail($normalizedEmail);

        if (null !== $user) {
            return $user;
        }

        $user = (new User())->setEmail($normalizedEmail);
        $this->userProvisioner->createDefaultWorkspaceForUser($user);
        $this->entityManager->persist($user);
        $this->entityManager->flush();

        return $user;
    }

    public function issueChallenge(User $user, string $purpose): CreatedChallenge
    {
        $now = new \DateTimeImmutable();
        $this->challengeRepository->invalidateActiveChallenges($user, $purpose, $now);

        $createdChallenge = $this->challengeFactory->create($user, $purpose, $now);

        $this->entityManager->persist($createdChallenge->challenge);
        $this->entityManager->flush();

        return $createdChallenge;
    }

    public function consumeByCode(string $email, string $code, string $purpose): ?LoginChallenge
    {
        $normalizedEmail = $this->emailNormalizer->normalize($email);
        $user = $this->userRepository->findOneByNormalizedEmail($normalizedEmail);

        if (null === $user) {
            return null;
        }

        $challenge = $this->challengeRepository->findLatestActiveCodeChallenge($user, $purpose, strtoupper(trim($code)), new \DateTimeImmutable());

        if (null === $challenge) {
            return null;
        }

        $challenge->setUsedAt(new \DateTimeImmutable());
        $this->entityManager->flush();

        return $challenge;
    }

    public function consumeByMagicLink(string $selector, string $token): ?LoginChallenge
    {
        $challenge = $this->challengeRepository->findActiveBySelector($selector, new \DateTimeImmutable());

        if (null === $challenge) {
            return null;
        }

        if (!hash_equals($challenge->getTokenHash(), hash('sha256', $token))) {
            return null;
        }

        $challenge->setUsedAt(new \DateTimeImmutable());
        $this->entityManager->flush();

        return $challenge;
    }
}
