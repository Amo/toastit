<?php

namespace App\Workspace;

use App\Entity\User;
use App\Security\EmailNormalizer;
use Doctrine\ORM\EntityManagerInterface;

final class UserProvisioner
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly EmailNormalizer $emailNormalizer,
    ) {
    }

    public function findOrCreateUserByEmail(string $email): User
    {
        $normalizedEmail = $this->emailNormalizer->normalize($email);
        $user = $this->entityManager->getRepository(User::class)->findOneBy(['email' => $normalizedEmail]);

        if ($user instanceof User) {
            return $user;
        }

        $user = (new User())->setEmail($normalizedEmail);
        $this->entityManager->persist($user);
        $this->entityManager->flush();

        return $user;
    }
}
