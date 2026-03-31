<?php

namespace App\Security;

use App\Entity\User;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class PinManager
{
    public function __construct(
        private readonly UserPasswordHasherInterface $passwordHasher,
    ) {
    }

    public function hashPin(User $user, string $pin): string
    {
        return $this->passwordHasher->hashPassword($user, $pin);
    }

    public function verifyPin(User $user, string $pin): bool
    {
        $hash = $user->getPinHash();

        if (null === $hash) {
            return false;
        }

        return $this->passwordHasher->isPasswordValid($user, $pin);
    }
}
