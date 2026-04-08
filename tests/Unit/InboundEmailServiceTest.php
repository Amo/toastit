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
use Twig\Environment;
use Twig\Loader\FilesystemLoader;
use League\CommonMark\CommonMarkConverter;

final class InboundEmailServiceTest extends TestCase
{
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
