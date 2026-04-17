<?php

namespace App\Tests\Unit;

use App\Entity\User;
use App\Mailer\TransactionalMailer;
use App\Profile\UserDateTimeFormatter;
use League\CommonMark\CommonMarkConverter;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

final class TransactionalMailerTest extends TestCase
{
    public function testSendTodoDigestNormalizesRelativeToastLinksToAbsoluteUrls(): void
    {
        $user = (new User())->setEmail('owner@example.com')->setFirstName('Owner');

        $mailerTransport = $this->createMock(MailerInterface::class);
        $mailerTransport
            ->expects(self::once())
            ->method('send')
            ->with(self::callback(function (Email $email): bool {
                self::assertStringContainsString('https://toastit.test/app/toasts/243', $email->getHtmlBody() ?? '');
                self::assertStringContainsString('https://toastit.test/app/toasts/243', $email->getTextBody() ?? '');

                return true;
            }));

        $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $urlGenerator
            ->expects(self::atLeastOnce())
            ->method('generate')
            ->with('app_spa', ['path' => 'toasts/243'], UrlGeneratorInterface::ABSOLUTE_URL)
            ->willReturn('https://toastit.test/app/toasts/243');

        $mailer = new TransactionalMailer(
            $mailerTransport,
            new Environment(new FilesystemLoader(dirname(__DIR__, 2).'/templates')),
            new CommonMarkConverter(),
            new UserDateTimeFormatter(),
            'hello@toastit.test',
            $urlGenerator,
        );

        $mailer->sendTodoDigest(
            $user,
            "## Top actions\n\n- Review [#243](/app/toasts/243)",
        );
    }
}
