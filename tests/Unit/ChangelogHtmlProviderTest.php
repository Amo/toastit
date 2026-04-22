<?php

namespace App\Tests\Unit;

use App\Release\ChangelogHtmlProvider;
use League\CommonMark\CommonMarkConverter;
use PHPUnit\Framework\TestCase;

final class ChangelogHtmlProviderTest extends TestCase
{
    public function testReturnsRenderedHtmlFromMarkdownChangelog(): void
    {
        $projectDir = sys_get_temp_dir().'/toastit-changelog-provider-'.bin2hex(random_bytes(6));
        mkdir($projectDir);
        file_put_contents($projectDir.'/CHANGELOG.md', "## 1.9.2\n\n- Fixed profile about section\n");

        $provider = new ChangelogHtmlProvider($projectDir, new CommonMarkConverter());

        self::assertStringContainsString('<h2>1.9.2</h2>', $provider->getCurrentHtml());
        self::assertStringContainsString('Fixed profile about section', $provider->getCurrentHtml());
    }

    public function testReturnsEmptyStringWhenChangelogIsMissing(): void
    {
        $projectDir = sys_get_temp_dir().'/toastit-changelog-provider-missing-'.bin2hex(random_bytes(6));
        mkdir($projectDir);

        $provider = new ChangelogHtmlProvider($projectDir, new CommonMarkConverter());

        self::assertSame('', $provider->getCurrentHtml());
    }
}
