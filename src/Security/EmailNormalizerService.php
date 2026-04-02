<?php

namespace App\Security;

final class EmailNormalizerService
{
    public function normalize(string $email): string
    {
        return mb_strtolower(trim($email));
    }
}
