<?php

namespace App\Release;

use League\CommonMark\CommonMarkConverter;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class ChangelogHtmlProvider
{
    public function __construct(
        #[Autowire('%kernel.project_dir%')]
        private readonly string $projectDir,
        private readonly CommonMarkConverter $markdownConverter,
    ) {
    }

    public function getCurrentHtml(): string
    {
        $changelogPath = sprintf('%s/CHANGELOG.md', $this->projectDir);
        if (!is_file($changelogPath)) {
            return '';
        }

        $markdown = trim((string) file_get_contents($changelogPath));
        if ('' === $markdown) {
            return '';
        }

        return $this->markdownConverter->convert($markdown)->getContent();
    }
}
