<?php

namespace App\Workspace;

use App\Entity\User;
use App\Entity\Workspace;
use App\Entity\WorkspaceMember;
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
        $this->createDefaultWorkspaceForUser($user);
        $this->entityManager->persist($user);
        $this->entityManager->flush();

        return $user;
    }

    public function createDefaultWorkspaceForUser(User $user): void
    {
        $workspace = (new Workspace())
            ->setName('My Toasts')
            ->setIsDefault(true)
            ->setOrganizer($user);

        $membership = (new WorkspaceMember())
            ->setUser($user)
            ->setIsOwner(true);

        $workspace->addMembership($membership);

        $this->entityManager->persist($workspace);
        $this->entityManager->persist($membership);
    }
}
