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
        return $this->urlGenerator->generate('app_spa', [
            'path' => ltrim($path, '/'),
        ], UrlGeneratorInterface::ABSOLUTE_URL);
    }
}
