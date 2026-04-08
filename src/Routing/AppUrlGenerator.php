<?php

namespace App\Routing;

use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class AppUrlGenerator
{
    public function __construct(
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly string $defaultUri,
    ) {
    }

    public function spaPath(string $path = ''): string
    {
        $relativePath = $this->urlGenerator->generate('app_spa', [
            'path' => ltrim($path, '/'),
        ]);

        return rtrim($this->defaultUri, '/').$relativePath;
    }
}
