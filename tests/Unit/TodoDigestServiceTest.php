<?php

namespace App\Tests\Unit;

use App\Ai\AiPromptTemplateService;
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
            ->with(self::anything(), self::logicalAnd(
                self::stringContains('Ship the digest'),
                self::stringContains('Delivery')
            ))
            ->willReturn("## Top 10 actions\n\n1. Ship the digest");

        $promptTemplate = $this->createMock(AiPromptTemplateService::class);
        $promptTemplate
            ->method('resolveSystemPrompt')
            ->willReturn('You are helping a Toastit user decide what to do next.');
        $promptTemplate
            ->method('resolveUserPromptTemplate')
            ->willReturnCallback(static function (string $code, string $fallback, array $variables = []): string {
                return implode("\n", [
                    sprintf('User: %s', (string) ($variables['user_display_name'] ?? '')),
                    sprintf('Email: %s', (string) ($variables['user_email'] ?? '')),
                    sprintf('Today: %s', (string) ($variables['today_date'] ?? '')),
                    'Assigned active actions:',
                    (string) ($variables['assigned_actions_text'] ?? ''),
                ]);
            });

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

        (new TodoDigestService($toastRepository, $xaiText, $mailer, new AssignedToastPriorityService(), $promptTemplate))->sendTodoDigest($user);
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

        $promptTemplate = $this->createMock(AiPromptTemplateService::class);
        $promptTemplate
            ->method('resolveSystemPrompt')
            ->willReturn('You are helping a Toastit user decide what to do next.');
        $promptTemplate
            ->method('resolveUserPromptTemplate')
            ->willReturn('Assigned active actions:');

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

        (new TodoDigestService($toastRepository, $xaiText, $mailer, new AssignedToastPriorityService(), $promptTemplate))->sendTodoDigest($user);
    }

    public function testSendWeeklySummaryUsesXaiWhenTasksExist(): void
    {
        $user = (new User())->setEmail('owner@example.com')->setFirstName('Owner');
        $workspace = (new Workspace())->setName('Operations')->setOrganizer($user);

        $createdToast = (new Toast())
            ->setWorkspace($workspace)
            ->setAuthor($user)
            ->setTitle('Prepare monthly report')
            ->setDescription('Gather KPI updates.')
            ->setOwner($user)
            ->setStatus(Toast::STATUS_PENDING);

        $completedToast = (new Toast())
            ->setWorkspace($workspace)
            ->setAuthor($user)
            ->setTitle('Close hiring loop')
            ->setDescription('Finalize decision.')
            ->setOwner($user)
            ->setStatus(Toast::STATUS_TOASTED)
            ->setStatusChangedAt(new \DateTimeImmutable('2026-04-10 10:00'));

        $toastRepository = $this->createMock(ToastRepository::class);
        $toastRepository
            ->expects(self::once())
            ->method('findCreatedByUserSince')
            ->with($user, self::isInstanceOf(\DateTimeImmutable::class))
            ->willReturn([$createdToast]);
        $toastRepository
            ->expects(self::once())
            ->method('findCreatedByUserAndCompletedSince')
            ->with($user, self::isInstanceOf(\DateTimeImmutable::class))
            ->willReturn([$completedToast]);
        $toastRepository
            ->expects(self::once())
            ->method('findAssignedToUserAndCompletedSince')
            ->with($user, self::isInstanceOf(\DateTimeImmutable::class))
            ->willReturn([$completedToast]);

        $xaiText = $this->createMock(XaiTextService::class);
        $xaiText
            ->expects(self::once())
            ->method('generateText')
            ->with(self::anything(), self::stringContains('Prepare monthly report'))
            ->willReturn("## Bilan de la semaine\n\n- Rapport pret.");

        $promptTemplate = $this->createMock(AiPromptTemplateService::class);
        $promptTemplate
            ->method('resolveSystemPrompt')
            ->willReturn('Produce a weekly operational summary in markdown.');
        $promptTemplate
            ->method('resolveUserPromptTemplate')
            ->willReturnCallback(static function (string $code, string $fallback, array $variables = []): string {
                return implode("\n", [
                    sprintf('User: %s', (string) ($variables['user_display_name'] ?? '')),
                    sprintf('Email: %s', (string) ($variables['user_email'] ?? '')),
                    sprintf('Window start: %s', (string) ($variables['window_start'] ?? '')),
                    sprintf('Window end: %s', (string) ($variables['window_end'] ?? '')),
                    (string) ($variables['created_by_user'] ?? ''),
                    (string) ($variables['created_and_completed'] ?? ''),
                    (string) ($variables['assigned_and_completed'] ?? ''),
                ]);
            });

        $mailerTransport = $this->createMock(MailerInterface::class);
        $mailerTransport
            ->expects(self::once())
            ->method('send')
            ->with(self::callback(function (Email $email): bool {
                self::assertSame('Toastit weekly operational summary', $email->getSubject());
                self::assertStringContainsString('Bilan de la semaine', $email->getTextBody() ?? '');

                return true;
            }));

        $mailer = new TransactionalMailer(
            $mailerTransport,
            new Environment(new FilesystemLoader(dirname(__DIR__, 2).'/templates')),
            new CommonMarkConverter(),
            'no-reply@toastit.local',
        );

        (new TodoDigestService($toastRepository, $xaiText, $mailer, new AssignedToastPriorityService(), $promptTemplate))->sendWeeklySummary($user);
    }
}
