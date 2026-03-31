<?php

namespace App\Security;

use App\Entity\LoginChallenge;

final readonly class CreatedChallenge
{
    public function __construct(
        public LoginChallenge $challenge,
        public string $plainToken,
    ) {
    }
}
