<?php

namespace App\Tests\Unit;

use App\Entity\Toast;
use App\Entity\ToastReplyToken;
use App\Entity\User;
use App\Entity\Workspace;
use App\Entity\WorkspaceMember;
use App\Mailer\TransactionalMailer;
use App\Meeting\XaiTextService;
use App\Repository\ToastRepository;
use App\Repository\ToastReplyTokenRepository;
use App\Repository\UserRepository;
use App\Repository\WorkspaceMemberRepository;
use App\Repository\WorkspaceRepository;
use App\Tests\Support\ReflectionHelper;
use App\Workspace\InboundEmailAddressService;
use App\Workspace\InboundEmailResult;
use App\Workspace\InboundEmailService;
use App\Workspace\InboundReplyAddressService;
use App\Workspace\InboxWorkspaceService;
use App\Workspace\AssignedToastPriorityService;
use App\Workspace\TodoDigestService;
use App\Workspace\ToastCreationService;
use App\Workspace\ToastDraftRefinementService;
use App\Workspace\ToastReplyTokenService;
use App\Workspace\ToastTransferService;
use App\Workspace\WorkspaceSuggestionService;
use App\Workspace\WorkspaceWorkflowService;
use App\Routing\AppUrlGenerator;
use App\Security\JwtTokenService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;
use League\CommonMark\CommonMarkConverter;

final class InboundEmailServiceTest extends TestCase
{
    public function testInboundToastRespectsUserAutoApplyPreferencesWhenDisabled(): void
    {
        $actor = (new User())
            ->setEmail('owner@example.com')
            ->setFirstName('Owner')
            ->setInboundEmailAlias('018f2e9a-9d9f-7f1f-8f7a-123456789def')
            ->setInboundAutoApplyReword(false)
            ->setInboundAutoApplyAssignee(false)
            ->setInboundAutoApplyDueDate(false)
            ->setInboundAutoApplyWorkspace(false);
        ReflectionHelper::setId($actor, 1);

        $suggestedOwner = (new User())
            ->setEmail('alex@example.com')
            ->setFirstName('Alex');
        ReflectionHelper::setId($suggestedOwner, 2);

        $workspace = (new Workspace())
            ->setName('Inbox')
            ->setOrganizer($actor)
            ->setIsInboxWorkspace(true)
            ->setIsSoloWorkspace(true);
        ReflectionHelper::setId($workspace, 10);
        $workspace->addMembership((new WorkspaceMember())->setUser($actor)->setIsOwner(true));
        $workspace->addMembership((new WorkspaceMember())->setUser($suggestedOwner));

        $targetWorkspace = (new Workspace())
            ->setName('Product Board')
            ->setOrganizer($actor);
        ReflectionHelper::setId($targetWorkspace, 20);
        $targetWorkspace->addMembership((new WorkspaceMember())->setUser($actor)->setIsOwner(true));
        $targetWorkspace->addMembership((new WorkspaceMember())->setUser($suggestedOwner));

        $writeEntityManager = $this->createMock(EntityManagerInterface::class);
        $writeEntityManager->expects(self::once())->method('persist')->with(self::isInstanceOf(Toast::class));
        $writeEntityManager->expects(self::once())->method('flush');

        $tokenEntityManager = $this->createMock(EntityManagerInterface::class);
        $tokenEntityManager->expects(self::once())->method('persist')->with(self::isInstanceOf(ToastReplyToken::class));
        $tokenEntityManager->expects(self::once())->method('flush');

        $tokenRepository = $this->createMock(ToastReplyTokenRepository::class);
        $tokenRepository
            ->expects(self::once())
            ->method('invalidateActiveTokens')
            ->with($actor, 0, ToastReplyToken::ACTION_REPHRASE, self::isInstanceOf(\DateTimeImmutable::class));

        $userRepository = $this->createMock(UserRepository::class);
        $userRepository
            ->expects(self::once())
            ->method('findOneByInboundEmailAlias')
            ->with('018f2e9a-9d9f-7f1f-8f7a-123456789def')
            ->willReturn($actor);

        $workspaceRepository = $this->createMock(WorkspaceRepository::class);
        $workspaceRepository
            ->method('findInboxWorkspaceForUser')
            ->with($actor)
            ->willReturn($workspace);
        $workspaceRepository
            ->method('findOneForUser')
            ->with(20, $actor)
            ->willReturn($targetWorkspace);

        $workspaceSuggestionRepository = $this->createMock(WorkspaceRepository::class);
        $workspaceSuggestionRepository
            ->method('findForUser')
            ->with($actor)
            ->willReturn([$targetWorkspace]);

        $mailerTransport = $this->createMock(MailerInterface::class);
        $mailerTransport
            ->expects(self::once())
            ->method('send')
            ->with(self::callback(static function (\Symfony\Component\Mime\RawMessage $message): bool {
                self::assertInstanceOf(Email::class, $message);
                self::assertStringContainsString('Current task result:', (string) $message->getTextBody());
                self::assertStringContainsString('Workspace: Inbox', (string) $message->getTextBody());

                return true;
            }));

        $transactionalMailer = new TransactionalMailer(
            $mailerTransport,
            new Environment(new FilesystemLoader(dirname(__DIR__, 2).'/templates')),
            new CommonMarkConverter(),
            'no-reply@toastit.local',
        );

        $service = new InboundEmailService(
            new InboundEmailAddressService('in.toastit.cc'),
            $userRepository,
            $workspaceRepository,
            $this->createMock(ToastRepository::class),
            new InboxWorkspaceService(
                $workspaceRepository,
                $this->createMock(WorkspaceMemberRepository::class),
                $this->createMock(EntityManagerInterface::class),
            ),
            new ToastCreationService($writeEntityManager),
            new TodoDigestService(
                $this->createMock(ToastRepository::class),
                new XaiTextService(new MockHttpClient([]), '', 'https://api.x.ai/v1', 'test-model', 30),
                $transactionalMailer,
                new AssignedToastPriorityService(),
            ),
            new ToastDraftRefinementService(
                new XaiTextService(
                    new MockHttpClient([
                        new MockResponse(<<<'JSON'
{"output_text":"TITLE: Clarify launch plan\nASSIGNEE: Alex\nDUE_ON: 2026-04-16\nDESCRIPTION:\n## Context\n- Rewritten by xAI.\n\n## Action\n- Confirm owner and due date."}
JSON),
                    ]),
                    'configured-key',
                    'https://api.x.ai/v1',
                    'test-model',
                    30,
                ),
                new WorkspaceWorkflowService(),
            ),
            new ToastReplyTokenService($tokenEntityManager, $tokenRepository),
            new InboundReplyAddressService('in.toastit.cc'),
            new WorkspaceSuggestionService(
                $workspaceSuggestionRepository,
                new XaiTextService(
                    new MockHttpClient([
                        new MockResponse(<<<'JSON'
{"output_text":"WORKSPACE: Product Board\nREASON: Better fit for product delivery ownership."}
JSON),
                    ]),
                    'configured-key',
                    'https://api.x.ai/v1',
                    'test-model',
                    30,
                ),
            ),
            $transactionalMailer,
            new ToastTransferService(new WorkspaceWorkflowService(), $this->createMock(EntityManagerInterface::class)),
            new WorkspaceWorkflowService(),
            new JwtTokenService('unit-test-secret'),
            new AppUrlGenerator($this->createMock(UrlGeneratorInterface::class), 'https://toastit.test'),
            $writeEntityManager,
        );

        $result = $service->ingest(
            'toast+018f2e9a-9d9f-7f1f-8f7a-123456789def@in.toastit.cc',
            'sender@example.com',
            'Raw inbound title',
            "Inbound body.\nNeed more structure.",
            null,
            '<message-id>',
            null,
            '<references>',
        );

        self::assertInstanceOf(InboundEmailResult::class, $result);
        self::assertSame('toast_created', $result->getKind());
        self::assertSame('Raw inbound title', $result->getToast()?->getTitle());
        self::assertSame('Inbox', $result->getToast()?->getWorkspace()->getName());
        self::assertNull($result->getToast()?->getOwner());
        self::assertNull($result->getToast()?->getDueAt());
    }

    public function testInboundToastIsAutomaticallyRewordedAndReplyToAllowsFurtherCommands(): void
    {
        $actor = (new User())
            ->setEmail('owner@example.com')
            ->setFirstName('Owner')
            ->setInboundEmailAlias('018f2e9a-9d9f-7f1f-8f7a-123456789abc');
        ReflectionHelper::setId($actor, 1);

        $suggestedOwner = (new User())
            ->setEmail('alex@example.com')
            ->setFirstName('Alex');
        ReflectionHelper::setId($suggestedOwner, 2);

        $workspace = (new Workspace())
            ->setName('Inbox')
            ->setOrganizer($actor)
            ->setIsInboxWorkspace(true)
            ->setIsSoloWorkspace(true);
        ReflectionHelper::setId($workspace, 10);
        $workspace->addMembership((new WorkspaceMember())->setUser($actor)->setIsOwner(true));
        $workspace->addMembership((new WorkspaceMember())->setUser($suggestedOwner));

        $targetWorkspace = (new Workspace())
            ->setName('Product Board')
            ->setOrganizer($actor);
        ReflectionHelper::setId($targetWorkspace, 20);
        $targetWorkspace->addMembership((new WorkspaceMember())->setUser($actor)->setIsOwner(true));
        $targetWorkspace->addMembership((new WorkspaceMember())->setUser($suggestedOwner));

        $writeEntityManager = $this->createMock(EntityManagerInterface::class);
        $writeEntityManager
            ->expects(self::once())
            ->method('persist')
            ->with(self::isInstanceOf(Toast::class));
        $writeEntityManager
            ->expects(self::once())
            ->method('flush');

        $tokenEntityManager = $this->createMock(EntityManagerInterface::class);
        $tokenEntityManager
            ->expects(self::once())
            ->method('persist')
            ->with(self::isInstanceOf(ToastReplyToken::class));
        $tokenEntityManager
            ->expects(self::once())
            ->method('flush');

        $tokenRepository = $this->createMock(ToastReplyTokenRepository::class);
        $tokenRepository
            ->expects(self::once())
            ->method('invalidateActiveTokens')
            ->with($actor, 0, ToastReplyToken::ACTION_REPHRASE, self::isInstanceOf(\DateTimeImmutable::class));

        $userRepository = $this->createMock(UserRepository::class);
        $userRepository
            ->expects(self::once())
            ->method('findOneByInboundEmailAlias')
            ->with('018f2e9a-9d9f-7f1f-8f7a-123456789abc')
            ->willReturn($actor);

        $workspaceRepository = $this->createMock(WorkspaceRepository::class);
        $workspaceRepository
            ->method('findInboxWorkspaceForUser')
            ->with($actor)
            ->willReturn($workspace);
        $workspaceRepository
            ->method('findOneForUser')
            ->with(20, $actor)
            ->willReturn($targetWorkspace);

        $mailerTransport = $this->createMock(MailerInterface::class);
        $mailerTransport
            ->expects(self::once())
            ->method('send')
            ->with(self::callback(static function (\Symfony\Component\Mime\RawMessage $message): bool {
                self::assertInstanceOf(Email::class, $message);
                self::assertNotEmpty($message->getReplyTo());
                self::assertStringContainsString('toast-reply+', $message->getReplyTo()[0]->getAddress());
                self::assertStringContainsString('Result (xAI auto-processed):', (string) $message->getTextBody());
                self::assertStringContainsString('Workspace: Product Board', (string) $message->getTextBody());
                self::assertStringContainsString('Assignee: Alex', (string) $message->getTextBody());
                self::assertStringContainsString('Due date: 2026-04-16', (string) $message->getTextBody());

                return true;
            }));

        $transactionalMailer = new TransactionalMailer(
            $mailerTransport,
            new Environment(new FilesystemLoader(dirname(__DIR__, 2).'/templates')),
            new CommonMarkConverter(),
            'no-reply@toastit.local',
        );

        $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $urlGenerator
            ->method('generate')
            ->willReturn('/email/action/mock-token');

        $workspaceSuggestionRepository = $this->createMock(WorkspaceRepository::class);
        $workspaceSuggestionRepository
            ->method('findForUser')
            ->with($actor)
            ->willReturn([$targetWorkspace]);

        $service = new InboundEmailService(
            new InboundEmailAddressService('in.toastit.cc'),
            $userRepository,
            $workspaceRepository,
            $this->createMock(ToastRepository::class),
            new InboxWorkspaceService(
                $workspaceRepository,
                $this->createMock(WorkspaceMemberRepository::class),
                $this->createMock(EntityManagerInterface::class),
            ),
            new ToastCreationService($writeEntityManager),
            new TodoDigestService(
                $this->createMock(ToastRepository::class),
                new XaiTextService(new MockHttpClient([]), '', 'https://api.x.ai/v1', 'test-model', 30),
                $transactionalMailer,
                new AssignedToastPriorityService(),
            ),
            new ToastDraftRefinementService(
                new XaiTextService(
                    new MockHttpClient([
                        new MockResponse(<<<'JSON'
{"output_text":"TITLE: Clarify launch plan\nASSIGNEE: Alex\nDUE_ON: 2026-04-16\nDESCRIPTION:\n## Context\n- Rewritten by xAI.\n\n## Action\n- Confirm owner and due date."}
JSON),
                    ]),
                    'configured-key',
                    'https://api.x.ai/v1',
                    'test-model',
                    30,
                ),
                new WorkspaceWorkflowService(),
            ),
            new ToastReplyTokenService($tokenEntityManager, $tokenRepository),
            new InboundReplyAddressService('in.toastit.cc'),
            new WorkspaceSuggestionService(
                $workspaceSuggestionRepository,
                new XaiTextService(
                    new MockHttpClient([
                        new MockResponse(<<<'JSON'
{"output_text":"WORKSPACE: Product Board\nREASON: Better fit for product delivery ownership."}
JSON),
                    ]),
                    'configured-key',
                    'https://api.x.ai/v1',
                    'test-model',
                    30,
                ),
            ),
            $transactionalMailer,
            new ToastTransferService(new WorkspaceWorkflowService(), $this->createMock(EntityManagerInterface::class)),
            new WorkspaceWorkflowService(),
            new JwtTokenService('unit-test-secret'),
            new AppUrlGenerator($urlGenerator, 'https://toastit.test'),
            $writeEntityManager,
        );

        $result = $service->ingest(
            'toast+018f2e9a-9d9f-7f1f-8f7a-123456789abc@in.toastit.cc',
            'sender@example.com',
            'Raw inbound title',
            "Inbound body.\nNeed more structure.",
            null,
            '<message-id>',
            null,
            '<references>',
        );

        self::assertInstanceOf(InboundEmailResult::class, $result);
        self::assertSame('toast_created', $result->getKind());
        self::assertSame('Clarify launch plan', $result->getToast()?->getTitle());
        self::assertSame('Product Board', $result->getToast()?->getWorkspace()->getName());
        self::assertSame($suggestedOwner, $result->getToast()?->getOwner());
        self::assertSame('2026-04-16', $result->getToast()?->getDueAt()?->format('Y-m-d'));
    }

    public function testRewordReplyUpdatesToastInPlace(): void
    {
        $actor = (new User())->setEmail('amaury@lerouxdelens.com')->setFirstName('Amaury');
        $suggestedOwner = (new User())->setEmail('owner@example.com')->setFirstName('Owner');
        ReflectionHelper::setId($actor, 1);
        ReflectionHelper::setId($suggestedOwner, 2);

        $workspace = (new Workspace())
            ->setName('Inbox')
            ->setOrganizer($actor)
            ->setIsSoloWorkspace(true)
            ->setIsDefault(true);
        $workspace->addMembership((new WorkspaceMember())->setUser($actor)->setIsOwner(true));
        $workspace->addMembership((new WorkspaceMember())->setUser($suggestedOwner));

        $toast = (new Toast())
            ->setWorkspace($workspace)
            ->setAuthor($actor)
            ->setTitle('Titre brouillon')
            ->setDescription('Description brouillon');
        ReflectionHelper::setId($toast, 10);

        $replyToken = (new ToastReplyToken())
            ->setUser($actor)
            ->setToast($toast)
            ->setSelector('selector')
            ->setTokenHash(hash('sha256', 'plain-token'));

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager
            ->expects(self::once())
            ->method('flush');

        $replyTokenRepository = $this->createMock(ToastReplyTokenRepository::class);
        $replyTokenRepository
            ->expects(self::once())
            ->method('findActiveBySelector')
            ->with('selector', self::isInstanceOf(\DateTimeImmutable::class))
            ->willReturn($replyToken);

        $toastReplyToken = new ToastReplyTokenService($entityManager, $replyTokenRepository);

        $toastDraftRefinement = new ToastDraftRefinementService(
            new XaiTextService(
                new MockHttpClient([
                    new MockResponse(<<<'JSON'
{"output_text":"TITLE: Décider le lancement\nASSIGNEE: Owner\nDUE_ON: 2026-04-15\nDESCRIPTION:\nContexte clarifié\n\nDécision attendue."}
JSON),
                ]),
                'configured-key',
                'https://api.x.ai/v1',
                'test-model',
                30,
            ),
            new WorkspaceWorkflowService(),
        );

        $mailerTransport = $this->createMock(MailerInterface::class);
        $mailerTransport
            ->expects(self::once())
            ->method('send');

        $transactionalMailer = new TransactionalMailer(
            $mailerTransport,
            new Environment(new FilesystemLoader(dirname(__DIR__, 2).'/templates')),
            new CommonMarkConverter(),
            'no-reply@toastit.local',
        );

        $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $urlGenerator
            ->method('generate')
            ->willReturn('/email/action/mock-token');

        $service = new InboundEmailService(
            new InboundEmailAddressService('in.toastit.cc'),
            $this->createMock(UserRepository::class),
            $this->createMock(WorkspaceRepository::class),
            $this->createMock(ToastRepository::class),
            new InboxWorkspaceService(
                $this->createMock(WorkspaceRepository::class),
                $this->createMock(WorkspaceMemberRepository::class),
                $this->createMock(EntityManagerInterface::class),
            ),
            new ToastCreationService($this->createMock(EntityManagerInterface::class)),
            new TodoDigestService(
                $this->createMock(ToastRepository::class),
                new XaiTextService(new MockHttpClient([]), '', 'https://api.x.ai/v1', 'test-model', 30),
                $transactionalMailer,
                new AssignedToastPriorityService(),
            ),
            $toastDraftRefinement,
            $toastReplyToken,
            new InboundReplyAddressService('in.toastit.cc'),
            new WorkspaceSuggestionService(
                $this->createMock(WorkspaceRepository::class),
                new XaiTextService(new MockHttpClient([]), '', 'https://api.x.ai/v1', 'test-model', 30),
            ),
            $transactionalMailer,
            new ToastTransferService(new WorkspaceWorkflowService(), $this->createMock(EntityManagerInterface::class)),
            new WorkspaceWorkflowService(),
            new JwtTokenService('unit-test-secret'),
            new AppUrlGenerator($urlGenerator, 'https://toastit.test'),
            $entityManager,
        );

        $result = $service->ingest(
            'toast-reply+selector-plain-token@in.toastit.cc',
            'sender@example.com',
            'Re: sujet',
            'reword',
            null,
            '<message-id>',
            null,
            '<references>',
        );

        self::assertInstanceOf(InboundEmailResult::class, $result);
        self::assertSame('todo_digest_sent', $result->getKind());
        self::assertSame('Décider le lancement', $toast->getTitle());
        self::assertSame("Contexte clarifié\n\nDécision attendue.", $toast->getDescription());
        self::assertSame($suggestedOwner, $toast->getOwner());
        self::assertSame('2026-04-15', $toast->getDueAt()?->format('Y-m-d'));
    }
}
