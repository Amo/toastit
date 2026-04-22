<?php

namespace App\Tests\Unit;

use App\Release\AppVersionProvider;
use PHPUnit\Framework\TestCase;

final class AppVersionProviderTest extends TestCase
{
    public function testReturnsTrimmedVersionFromVersionFile(): void
    {
        $projectDir = sys_get_temp_dir().'/toastit-app-version-provider-'.bin2hex(random_bytes(6));
        mkdir($projectDir);
        file_put_contents($projectDir.'/VERSION', "1.9.2 \n");

        $provider = new AppVersionProvider($projectDir);

        self::assertSame('1.9.2', $provider->getCurrentVersion());
    }

    public function testReturnsUnknownWhenVersionFileIsMissing(): void
    {
        $projectDir = sys_get_temp_dir().'/toastit-app-version-provider-missing-'.bin2hex(random_bytes(6));
        mkdir($projectDir);

        $provider = new AppVersionProvider($projectDir);

        self::assertSame('unknown', $provider->getCurrentVersion());
    }
}
