<?php

namespace App\Release;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class AppVersionProvider
{
    public function __construct(
        #[Autowire('%kernel.project_dir%')]
        private readonly string $projectDir,
    ) {
    }

    public function getCurrentVersion(): string
    {
        $versionPath = sprintf('%s/VERSION', $this->projectDir);
        if (!is_file($versionPath)) {
            return 'unknown';
        }

        $version = trim((string) file_get_contents($versionPath));

        return '' !== $version ? $version : 'unknown';
    }
}
