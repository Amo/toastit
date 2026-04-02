<?php

namespace App\Security;

use App\Entity\LoginChallenge;

final readonly class LoginChallengeResult
{
    public function __construct(
        public LoginChallenge $challenge,
        public string $plainToken,
    ) {
    }
}
