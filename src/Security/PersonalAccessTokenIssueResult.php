<?php

namespace App\Security;

use App\Entity\PersonalAccessToken;

final readonly class PersonalAccessTokenIssueResult
{
    public function __construct(
        public PersonalAccessToken $token,
        public string $plainTextToken,
    ) {
    }
}

