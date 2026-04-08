<?php

namespace App\Tests\Unit;

use App\Entity\Toast;
use App\Entity\User;
use App\Entity\Workspace;
use App\Mailer\TransactionalMailer;
use App\Meeting\XaiTextService;
use App\Repository\ToastRepository;
use App\Workspace\AssignedToastPriorityService;
use App\Workspace\TodoDigestService;
use League\CommonMark\CommonMarkConverter;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

final class TodoDigestServiceTest extends TestCase
{
    public function testSendTodoDigestUsesXaiWhenAssignedActionsExist(): void
    {
        $user = (new User())->setEmail('owner@example.com')->setFirstName('Owner');
        $workspace = (new Workspace())->setName('Delivery')->setOrganizer($user);

        $toast = (new Toast())
            ->setWorkspace($workspace)
            ->setAuthor($user)
            ->setTitle('Ship the digest')
            ->setDescription('Send the next digest to stakeholders.')
            ->setOwner($user)
            ->setDueAt(new \DateTimeImmutable('2026-04-10'));

        $toastRepository = $this->createMock(ToastRepository::class);
        $toastRepository
            ->expects(self::once())
            ->method('findAssignedActiveForUser')
            ->with($user)
            ->willReturn([$toast]);

        $xaiText = $this->createMock(XaiTextService::class);
        $xaiText
            ->expects(self::once())
            ->method('generateText')
            ->with(
                self::stringContains('Top 10 actions'),
                self::logicalAnd(
                    self::stringContains('Ship the digest'),
                    self::stringContains('Delivery')
                ),
            )
            ->willReturn("## Top 10 actions\n\n1. Ship the digest");

        $mailerTransport = $this->createMock(MailerInterface::class);
        $mailerTransport
            ->expects(self::once())
            ->method('send')
            ->with(self::callback(function (Email $email): bool {
                self::assertSame('Toastit todo digest', $email->getSubject());
                self::assertStringContainsString('Ship the digest', $email->getTextBody() ?? '');

                return true;
            }));

        $mailer = new TransactionalMailer(
            $mailerTransport,
            new Environment(new FilesystemLoader(dirname(__DIR__, 2).'/templates')),
            new CommonMarkConverter(),
            'no-reply@toastit.local',
        );

        (new TodoDigestService($toastRepository, $xaiText, $mailer, new AssignedToastPriorityService()))->sendTodoDigest($user);
    }

    public function testSendTodoDigestFallsBackWhenNoAssignedActionsExist(): void
    {
        $user = (new User())->setEmail('owner@example.com');

        $toastRepository = $this->createMock(ToastRepository::class);
        $toastRepository
            ->expects(self::once())
            ->method('findAssignedActiveForUser')
            ->with($user)
            ->willReturn([]);

        $xaiText = $this->createMock(XaiTextService::class);
        $xaiText->expects(self::never())->method('generateText');

        $mailerTransport = $this->createMock(MailerInterface::class);
        $mailerTransport
            ->expects(self::once())
            ->method('send')
            ->with(self::callback(function (Email $email): bool {
                self::assertStringContainsString('no active assigned actions', $email->getTextBody() ?? '');

                return true;
            }));

        $mailer = new TransactionalMailer(
            $mailerTransport,
            new Environment(new FilesystemLoader(dirname(__DIR__, 2).'/templates')),
            new CommonMarkConverter(),
            'no-reply@toastit.local',
        );

        (new TodoDigestService($toastRepository, $xaiText, $mailer, new AssignedToastPriorityService()))->sendTodoDigest($user);
    }
}
