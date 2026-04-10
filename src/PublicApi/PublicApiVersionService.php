<?php

namespace App\PublicApi;

final class PublicApiVersionService
{
    public const MEDIA_TYPE = 'application/vnd.toastit.public+json';
    public const VERSION = '1';

    public function __construct(
        private readonly string $publicApiHost,
    ) {
    }

    public function isSupportedAcceptHeader(string $acceptHeader): bool
    {
        foreach (explode(',', $acceptHeader) as $acceptPart) {
            $segments = array_values(array_filter(array_map('trim', explode(';', trim($acceptPart)))));

            if ([] === $segments) {
                continue;
            }

            $mediaType = strtolower((string) ($segments[0] ?? ''));
            if ($mediaType !== self::MEDIA_TYPE) {
                continue;
            }

            $version = null;
            foreach (array_slice($segments, 1) as $parameter) {
                [$key, $value] = array_pad(explode('=', $parameter, 2), 2, null);
                if (null === $key || null === $value || strtolower(trim($key)) !== 'version') {
                    continue;
                }

                $version = trim(trim($value), '"');
                break;
            }

            if (self::VERSION === $version) {
                return true;
            }
        }

        return false;
    }

    public function buildExpectedAcceptValue(): string
    {
        return sprintf('%s; version=%s', self::MEDIA_TYPE, self::VERSION);
    }

    public function isPublicApiHost(string $host): bool
    {
        return strtolower(trim($host)) === strtolower(trim($this->publicApiHost));
    }
}
