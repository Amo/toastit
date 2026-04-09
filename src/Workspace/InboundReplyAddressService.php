<?php

namespace App\Workspace;

final class InboundReplyAddressService
{
    private const REPLY_PREFIX = 'toast-reply+';

    public function __construct(
        private readonly string $inboundEmailDomain,
    ) {
    }

    public function buildAddress(string $selector, string $token): ?string
    {
        if ('' === $this->inboundEmailDomain) {
            return null;
        }

        return sprintf('%s%s-%s@%s', self::REPLY_PREFIX, $selector, $token, $this->inboundEmailDomain);
    }

    /**
     * @return array{selector: string, token: string}|null
     */
    public function parseAddress(string $recipient): ?array
    {
        $recipient = $this->normalizeRecipient($recipient);
        if (null === $recipient) {
            return null;
        }

        $parts = explode('@', $recipient, 2);
        if (2 !== count($parts)) {
            return null;
        }

        [$localPart, $domain] = $parts;
        $normalizedLocalPart = mb_strtolower($localPart);
        if (mb_strtolower($domain) !== mb_strtolower($this->inboundEmailDomain)) {
            return null;
        }

        if (!str_starts_with($normalizedLocalPart, self::REPLY_PREFIX)) {
            return null;
        }

        $tokenParts = explode('-', substr($normalizedLocalPart, strlen(self::REPLY_PREFIX)), 2);
        if (2 !== count($tokenParts)) {
            return null;
        }

        return [
            'selector' => $tokenParts[0],
            'token' => $tokenParts[1],
        ];
    }

    private function normalizeRecipient(string $recipient): ?string
    {
        $recipient = trim($recipient);
        if ('' === $recipient) {
            return null;
        }

        if (preg_match('/<([^<>]+)>/', $recipient, $matches)) {
            $recipient = trim((string) $matches[1]);
        }

        if (str_starts_with(mb_strtolower($recipient), 'mailto:')) {
            $recipient = trim(substr($recipient, 7));
        }

        $recipient = trim($recipient, " \t\n\r\0\x0B\"'");
        if ('' === $recipient) {
            return null;
        }

        return false !== filter_var($recipient, FILTER_VALIDATE_EMAIL) ? $recipient : null;
    }
}
