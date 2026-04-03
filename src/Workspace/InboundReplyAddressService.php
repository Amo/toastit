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
        $recipient = trim($recipient);
        if ('' === $recipient) {
            return null;
        }

        $parts = explode('@', $recipient, 2);
        if (2 !== count($parts)) {
            return null;
        }

        [$localPart, $domain] = $parts;
        if (mb_strtolower($domain) !== mb_strtolower($this->inboundEmailDomain)) {
            return null;
        }

        if (!str_starts_with($localPart, self::REPLY_PREFIX)) {
            return null;
        }

        $tokenParts = explode('-', substr($localPart, strlen(self::REPLY_PREFIX)), 2);
        if (2 !== count($tokenParts)) {
            return null;
        }

        return [
            'selector' => $tokenParts[0],
            'token' => $tokenParts[1],
        ];
    }
}
