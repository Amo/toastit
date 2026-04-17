<?php

namespace App\Tests\Unit;

use App\Ai\AiPromptTemplateService;
use App\Entity\Toast;
use App\Entity\ToastComment;
use App\Entity\User;
use App\Entity\Workspace;
use App\Mailer\TransactionalMailer;
use App\Meeting\XaiTextService;
use App\Profile\UserDateTimeFormatter;
use App\Repository\ToastCommentRepository;
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

        $commentRepository = $this->createMock(ToastCommentRepository::class);

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

        $service = new TodoDigestService(
            $toastRepository,
            $commentRepository,
            $xaiText,
            $this->createMailer($mailerTransport),
            new AssignedToastPriorityService(),
            $promptTemplate,
        );

        $service->sendTodoDigest($user);
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

        $commentRepository = $this->createMock(ToastCommentRepository::class);

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

        $service = new TodoDigestService(
            $toastRepository,
            $commentRepository,
            $xaiText,
            $this->createMailer($mailerTransport),
            new AssignedToastPriorityService(),
            $promptTemplate,
        );

        $service->sendTodoDigest($user);
    }

    public function testSendDailyCollaborationRecapBuildsActionFirstStructuredEmail(): void
    {
        $user = (new User())->setEmail('owner@example.com')->setFirstName('Owner');
        $collaborator = (new User())->setEmail('alice@example.com')->setFirstName('Alice');
        $workspace = (new Workspace())->setName('Operations')->setOrganizer($user);

        $todayToast = (new Toast())
            ->setWorkspace($workspace)
            ->setAuthor($collaborator)
            ->setOwner($user)
            ->setTitle('Call blocked customer')
            ->setStatus(Toast::STATUS_READY)
            ->setDueAt(new \DateTimeImmutable('today'));
        $todayToast->setIsBoosted(true);

        $overdueToast = (new Toast())
            ->setWorkspace($workspace)
            ->setAuthor($user)
            ->setOwner($user)
            ->setTitle('Close hiring loop')
            ->setStatus(Toast::STATUS_PENDING)
            ->setDueAt(new \DateTimeImmutable('yesterday'));

        $completedToast = (new Toast())
            ->setWorkspace($workspace)
            ->setAuthor($user)
            ->setOwner($user)
            ->setTitle('Prepare board update')
            ->setStatus(Toast::STATUS_TOASTED)
            ->setStatusChangedAt(new \DateTimeImmutable('yesterday 17:30'));

        $comment = (new ToastComment())
            ->setToast($todayToast)
            ->setAuthor($collaborator)
            ->setContent('Can you confirm the final callback slot for today?');
        $todayToast->addComment($comment);

        $toastRepository = $this->createMock(ToastRepository::class);
        $toastRepository
            ->expects(self::once())
            ->method('findInvolvedToastIdsForUser')
            ->with($user, 800)
            ->willReturn([11, 12]);
        $toastRepository
            ->expects(self::once())
            ->method('findStatusChangedForToastIdsBetween')
            ->with([11, 12], self::isInstanceOf(\DateTimeImmutable::class), self::isInstanceOf(\DateTimeImmutable::class))
            ->willReturn([$completedToast]);
        $toastRepository
            ->expects(self::once())
            ->method('findAssignedActionableForUser')
            ->with($user, 80)
            ->willReturn([$todayToast, $overdueToast]);

        $commentRepository = $this->createMock(ToastCommentRepository::class);
        $commentRepository
            ->expects(self::once())
            ->method('findForToastIdsBetween')
            ->with([11, 12], self::isInstanceOf(\DateTimeImmutable::class), self::isInstanceOf(\DateTimeImmutable::class), 500)
            ->willReturn([$comment]);

        $xaiText = $this->createMock(XaiTextService::class);
        $xaiText
            ->expects(self::once())
            ->method('generateText')
            ->with(
                self::logicalAnd(
                    self::stringContains('Actions for today'),
                    self::stringContains('Never repeat the same toast in both Actions for today and Attention points.')
                ),
                self::logicalAnd(
                    self::stringContains('Call blocked customer'),
                    self::stringContains('Close hiring loop'),
                    self::stringContains('Prepare board update')
                )
            )
            ->willReturn(implode("\n", [
                '## Daily collaboration recap (2026-04-16)',
                '',
                '### Actions for today',
                '- **Call blocked customer**',
                '  - Workspace: Operations',
                '  - Due date: today',
                '  - Signals: ready to move, boosted',
                '- **Close hiring loop**',
                '  - Workspace: Operations',
                '  - Due date: overdue since 2026-04-16',
                '',
                '### Attention points',
                '- **Prepare board update**',
                '  - Workspace: Operations',
                '  - Attention: completed yesterday',
                '',
                '### Yesterday in numbers',
                '- 1 status change on involved toasts yesterday.',
                '',
                '### Yesterday highlights',
                '- **Prepare board update** moved to completed in Operations.',
            ]));

        $promptTemplate = $this->createMock(AiPromptTemplateService::class);
        $promptTemplate
            ->method('resolveSystemPrompt')
            ->willReturn('');
        $promptTemplate
            ->method('resolveUserPromptTemplate')
            ->willReturnCallback(static function (string $code, string $fallback, array $variables = []): string {
                return implode("\n", [
                    sprintf('User: %s', (string) ($variables['user_display_name'] ?? '')),
                    sprintf('Email: %s', (string) ($variables['user_email'] ?? '')),
                    sprintf('Day covered: %s', (string) ($variables['day_covered'] ?? '')),
                    (string) ($variables['status_updates'] ?? ''),
                    (string) ($variables['collaborative_comments'] ?? ''),
                    (string) ($variables['assigned_today'] ?? ''),
                    (string) ($variables['today_and_upcoming'] ?? ''),
                ]);
            });

        $mailerTransport = $this->createMock(MailerInterface::class);
        $mailerTransport
            ->expects(self::once())
            ->method('send')
            ->with(self::callback(function (Email $email): bool {
                $body = $email->getTextBody() ?? '';

                self::assertSame('Toastit daily collaboration recap', $email->getSubject());
                self::assertStringContainsString('### Actions for today', $body);
                self::assertStringContainsString('### Attention points', $body);
                self::assertStringContainsString('### Yesterday in numbers', $body);
                self::assertStringContainsString('### Yesterday highlights', $body);
                self::assertStringContainsString('- **Call blocked customer**', $body);
                self::assertStringContainsString('  - Workspace: Operations', $body);
                self::assertStringContainsString('  - Due date: today', $body);
                self::assertStringContainsString('- **Close hiring loop**', $body);
                self::assertSame(1, substr_count($body, '- **Close hiring loop**'));
                self::assertStringContainsString('- **Prepare board update**', $body);
                self::assertStringContainsString('  - Attention: completed yesterday', $body);
                self::assertStringContainsString('Toastit helps teams turn ideas, signals, and requests into visible actions and follow-ups.', $body);
                self::assertStringContainsString('hello@toastit.cc', $body);

                return true;
            }));

        $service = new TodoDigestService(
            $toastRepository,
            $commentRepository,
            $xaiText,
            $this->createMailer($mailerTransport),
            new AssignedToastPriorityService(),
            $promptTemplate,
        );

        $service->sendDailyCollaborationRecap($user, new \DateTimeImmutable('yesterday'));
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

        $commentRepository = $this->createMock(ToastCommentRepository::class);

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

        $service = new TodoDigestService(
            $toastRepository,
            $commentRepository,
            $xaiText,
            $this->createMailer($mailerTransport),
            new AssignedToastPriorityService(),
            $promptTemplate,
        );

        $service->sendWeeklySummary($user);
    }

    private function createMailer(MailerInterface $mailerTransport): TransactionalMailer
    {
        return new TransactionalMailer(
            $mailerTransport,
            new Environment(new FilesystemLoader(dirname(__DIR__, 2).'/templates')),
            new CommonMarkConverter(),
            new UserDateTimeFormatter(),
            'no-reply@toastit.local',
        );
    }
}
