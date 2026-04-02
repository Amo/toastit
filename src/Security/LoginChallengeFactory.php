<?php

namespace App\Security;

use App\Entity\LoginChallenge;
use App\Entity\User;

final class LoginChallengeFactory
{
    public function create(User $user, string $purpose, \DateTimeImmutable $now): LoginChallengeResult
    {
        $selector = bin2hex(random_bytes(8));
        $token = bin2hex(random_bytes(16));
        $code = $this->generateCode();

        $challenge = (new LoginChallenge())
            ->setUser($user)
            ->setPurpose($purpose)
            ->setSelector($selector)
            ->setCode($code)
            ->setTokenHash(hash('sha256', $token))
            ->setExpiresAt($now->modify('+10 minutes'));

        return new LoginChallengeResult($challenge, $token);
    }

    private function generateCode(): string
    {
        $code = '';

        for ($index = 0; $index < 6; ++$index) {
            $code .= (string) random_int(0, 9);
        }

        return $code;
    }
}
