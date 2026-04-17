<?php

namespace App\Routing;

use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class AppUrlGenerator
{
    public function __construct(
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
    }

    public function spaPath(string $path = ''): string
    {
        $normalizedPath = ltrim($path, '/');
        if (!str_starts_with($normalizedPath, 'app/')) {
            $normalizedPath = sprintf('app/%s', $normalizedPath);
        }

        return $this->urlGenerator->generate('app_spa', [
            'path' => $normalizedPath,
        ], UrlGeneratorInterface::ABSOLUTE_URL);
    }
}
