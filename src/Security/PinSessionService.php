<?php

namespace App\Security;

use Symfony\Component\HttpFoundation\RequestStack;

final class PinSessionService
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
        $timestamp = $this->getVerifiedAtTimestamp();

        if (!is_int($timestamp)) {
            return false;
        }

        return (time() - $timestamp) < 3600;
    }

    public function getVerifiedAtTimestamp(): ?int
    {
        $timestamp = $this->requestStack->getSession()->get(self::PIN_VERIFIED_AT);

        return is_int($timestamp) ? $timestamp : null;
    }

    public function getExpiresAtTimestamp(): ?int
    {
        $verifiedAt = $this->getVerifiedAtTimestamp();

        if (null === $verifiedAt) {
            return null;
        }

        return $verifiedAt + 3600;
    }
}
