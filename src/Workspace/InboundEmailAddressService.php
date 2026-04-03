<?php

namespace App\Workspace;

use App\Entity\User;

final class InboundEmailAddressService
{
    private const LOCAL_PART_PREFIX = 'toast+';

    public function __construct(
        private readonly string $inboundEmailDomain,
    ) {
    }

    public function buildAddressForUser(User $user): ?string
    {
        $email = $user->getPublicEmail();
        if (null === $email || '' === $this->inboundEmailDomain) {
            return null;
        }

        return sprintf(
            '%s%s@%s',
            self::LOCAL_PART_PREFIX,
            $this->encodeEmail($email),
            $this->inboundEmailDomain,
        );
    }

    public function resolveUserEmail(string $recipient): ?string
    {
        $recipient = trim($recipient);
        if ('' === $recipient) {
            return null;
        }

        $parts = explode('@', $recipient, 2);
        if (2 !== \count($parts)) {
            return null;
        }

        [$localPart, $domain] = $parts;

        if ('' === $this->inboundEmailDomain || mb_strtolower($domain) !== mb_strtolower($this->inboundEmailDomain)) {
            return null;
        }

        if (!str_starts_with($localPart, self::LOCAL_PART_PREFIX)) {
            return null;
        }

        $encodedEmail = substr($localPart, \strlen(self::LOCAL_PART_PREFIX));
        if ('' === $encodedEmail) {
            return null;
        }

        return $this->decodeEmail($encodedEmail);
    }

    private function encodeEmail(string $email): string
    {
        return rtrim(strtr(base64_encode($email), '+/', '-_'), '=');
    }

    private function decodeEmail(string $encodedEmail): ?string
    {
        $remainder = \strlen($encodedEmail) % 4;
        if (0 !== $remainder) {
            $encodedEmail .= str_repeat('=', 4 - $remainder);
        }

        $decodedEmail = base64_decode(strtr($encodedEmail, '-_', '+/'), true);

        if (false === $decodedEmail || !filter_var($decodedEmail, FILTER_VALIDATE_EMAIL)) {
            return null;
        }

        return mb_strtolower(trim($decodedEmail));
    }
}
