<?php

namespace App\Security;

use Symfony\Component\HttpFoundation\RequestStack;

final class PinSessionManager
{
    private const PIN_VERIFIED_AT = 'security.pin_verified_at';

    public function __construct(
        private readonly RequestStack $requestStack,
    ) {
    }

    public function markVerified(): void
    {
        $this->requestStack->getSession()->set(self::PIN_VERIFIED_AT, time());
    }

    public function clear(): void
    {
        $this->requestStack->getSession()->remove(self::PIN_VERIFIED_AT);
    }

    public function isVerified(): bool
    {
        $timestamp = $this->requestStack->getSession()->get(self::PIN_VERIFIED_AT);

        if (!is_int($timestamp)) {
            return false;
        }

        return (time() - $timestamp) < 3600;
    }
}
