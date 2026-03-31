<?php

namespace App\Security;

use App\Entity\ApiRefreshToken;

final readonly class RefreshTokenValidation
{
    public function __construct(
        public ?ApiRefreshToken $refreshToken,
        public ?string $error,
    ) {
    }
}
