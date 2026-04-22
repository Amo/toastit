<?php

namespace App\Tests\Unit;

use App\Api\ProfilePayloadBuilder;
use App\Entity\User;
use App\Profile\AvatarStorageService;
use App\Profile\AvatarUrlService;
use App\Profile\UserDateTimeFormatter;
use App\Release\AppVersionProvider;
use App\Release\ChangelogHtmlProvider;
use App\Repository\WorkspaceRepository;
use App\Tests\Support\ReflectionHelper;
use App\Workspace\InboundEmailAddressService;
use League\Flysystem\FilesystemOperator;
use League\CommonMark\CommonMarkConverter;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class ProfilePayloadBuilderTest extends TestCase
{
    public function testBuildProfileIncludesReleaseMetadata(): void
    {
        $user = (new User())
            ->setEmail('owner@example.com')
            ->setFirstName('Owner')
            ->setLastName('User');
        ReflectionHelper::setId($user, 1);

        $workspaceRepository = $this->createMock(WorkspaceRepository::class);
        $workspaceRepository
            ->expects(self::once())
            ->method('findInboxWorkspaceForUser')
            ->with($user)
            ->willReturn(null);

        $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $urlGenerator->expects(self::never())->method('generate');
        $filesystem = $this->createStub(FilesystemOperator::class);
        $avatarUrl = new AvatarUrlService($urlGenerator, new AvatarStorageService($filesystem, sys_get_temp_dir()));

        $projectDir = sys_get_temp_dir().'/toastit-profile-payload-builder-'.bin2hex(random_bytes(6));
        mkdir($projectDir);
        file_put_contents($projectDir.'/VERSION', "1.9.2\n");
        file_put_contents($projectDir.'/CHANGELOG.md', "## 1.9.2\n\n- Fix profile about section\n");

        $builder = new ProfilePayloadBuilder(
            $avatarUrl,
            new UserDateTimeFormatter(),
            $workspaceRepository,
            new InboundEmailAddressService('inbound.toastit.test'),
            new AppVersionProvider($projectDir),
            new ChangelogHtmlProvider($projectDir, new CommonMarkConverter()),
        );

        $payload = $builder->buildProfile($user, []);

        self::assertSame('1.9.2', $payload['about']['appVersion']);
        self::assertStringContainsString('Fix profile about section', $payload['about']['changelogHtml']);
    }
}
