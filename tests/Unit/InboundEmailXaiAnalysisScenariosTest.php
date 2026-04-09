<?php

namespace App\Tests\Unit;

use App\Ai\AiPromptTemplateService;
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
use App\Routing\AppUrlGenerator;
use App\Security\JwtTokenService;
use App\Tests\Support\ReflectionHelper;
use App\Workspace\AssignedToastPriorityService;
use App\Workspace\InboundEmailAddressService;
use App\Workspace\InboundEmailResult;
use App\Workspace\InboundEmailService;
use App\Workspace\InboundReplyAddressService;
use App\Workspace\InboxWorkspaceService;
use App\Workspace\TodoDigestService;
use App\Workspace\ToastCreationService;
use App\Workspace\ToastDraftRefinementService;
use App\Workspace\ToastReplyTokenService;
use App\Workspace\ToastTransferService;
use App\Workspace\WorkspaceSuggestionService;
use App\Workspace\WorkspaceWorkflowService;
use Doctrine\ORM\EntityManagerInterface;
use League\CommonMark\CommonMarkConverter;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\RawMessage;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

final class InboundEmailXaiAnalysisScenariosTest extends TestCase
{
    #[DataProvider('scenarioProvider')]
    public function testInboundEmailXaiScenarioProducesExpectedToast(
        string $subject,
        string $textBody,
        array $userPreferences,
        string $refinementOutput,
        string $workspaceSuggestionOutput,
        ?string $defaultWorkspaceName,
        array $expected,
    ): void {
        $author = (new User())
            ->setEmail('owner@example.com')
            ->setFirstName('Owner')
            ->setInboundEmailAlias('018f2e9a-9d9f-7f1f-8f7a-123456789abc')
            ->setInboundAutoApplyReword($userPreferences['reword'])
            ->setInboundAutoApplyAssignee($userPreferences['assignee'])
            ->setInboundAutoApplyDueDate($userPreferences['dueDate'])
            ->setInboundAutoApplyWorkspace($userPreferences['workspace']);
        ReflectionHelper::setId($author, 1);

        $suggestedOwner = (new User())
            ->setEmail('alex@example.com')
            ->setFirstName('Alex');
        ReflectionHelper::setId($suggestedOwner, 2);

        $inboxWorkspace = (new Workspace())
            ->setName('Inbox')
            ->setOrganizer($author)
            ->setIsInboxWorkspace(true)
            ->setIsSoloWorkspace(true);
        ReflectionHelper::setId($inboxWorkspace, 10);
        $inboxWorkspace->addMembership((new WorkspaceMember())->setUser($author)->setIsOwner(true));
        $inboxWorkspace->addMembership((new WorkspaceMember())->setUser($suggestedOwner));

        $suggestedWorkspace = (new Workspace())
            ->setName('Professional Network')
            ->setOrganizer($author);
        ReflectionHelper::setId($suggestedWorkspace, 20);
        $suggestedWorkspace->addMembership((new WorkspaceMember())->setUser($author)->setIsOwner(true));
        $suggestedWorkspace->addMembership((new WorkspaceMember())->setUser($suggestedOwner));

        $defaultWorkspace = null;
        if (null !== $defaultWorkspaceName) {
            $defaultWorkspace = (new Workspace())
                ->setName($defaultWorkspaceName)
                ->setOrganizer($author)
                ->setIsDefault(true);
            ReflectionHelper::setId($defaultWorkspace, 30);
            $defaultWorkspace->addMembership((new WorkspaceMember())->setUser($author)->setIsOwner(true));
            $defaultWorkspace->addMembership((new WorkspaceMember())->setUser($suggestedOwner));
        }

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
            ->with($author, 0, ToastReplyToken::ACTION_REPHRASE, self::isInstanceOf(\DateTimeImmutable::class));

        $userRepository = $this->createMock(UserRepository::class);
        $userRepository
            ->expects(self::once())
            ->method('findOneByInboundEmailAlias')
            ->with('018f2e9a-9d9f-7f1f-8f7a-123456789abc')
            ->willReturn($author);

        $workspaceRepository = $this->createMock(WorkspaceRepository::class);
        $workspaceRepository
            ->method('findInboxWorkspaceForUser')
            ->with($author)
            ->willReturn($inboxWorkspace);
        $workspaceRepository
            ->method('findOneForUser')
            ->willReturnCallback(static function (int $workspaceId, User $user) use ($author, $suggestedWorkspace, $defaultWorkspace) {
                if ($user->getId() !== $author->getId()) {
                    return null;
                }

                if ($workspaceId === $suggestedWorkspace->getId()) {
                    return $suggestedWorkspace;
                }

                if ($defaultWorkspace instanceof Workspace && $workspaceId === $defaultWorkspace->getId()) {
                    return $defaultWorkspace;
                }

                return null;
            });
        $workspaceRepository
            ->method('findDefaultWorkspaceForUser')
            ->with($author)
            ->willReturn($defaultWorkspace);

        $workspaceSuggestionRepository = $this->createMock(WorkspaceRepository::class);
        $workspaceSuggestionCandidates = [$suggestedWorkspace];
        if ($defaultWorkspace instanceof Workspace) {
            $workspaceSuggestionCandidates[] = $defaultWorkspace;
        }
        $workspaceSuggestionRepository
            ->method('findForUser')
            ->with($author)
            ->willReturn($workspaceSuggestionCandidates);

        $mailerTransport = $this->createMock(MailerInterface::class);
        $mailerTransport
            ->expects(self::once())
            ->method('send')
            ->with(self::isInstanceOf(RawMessage::class));

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
                $this->createPromptTemplateService(['todo_digest_system' => 'todo digest system prompt']),
            ),
            new ToastDraftRefinementService(
                new XaiTextService(
                    new MockHttpClient([
                        new MockResponse(json_encode(['output_text' => $refinementOutput], JSON_THROW_ON_ERROR)),
                    ]),
                    'configured-key',
                    'https://api.x.ai/v1',
                    'test-model',
                    30,
                ),
                new WorkspaceWorkflowService(),
                $this->createPromptTemplateService(['toast_draft_refinement_system' => 'refinement system prompt']),
            ),
            new ToastReplyTokenService($tokenEntityManager, $tokenRepository),
            new InboundReplyAddressService('in.toastit.cc'),
            new WorkspaceSuggestionService(
                $workspaceSuggestionRepository,
                new XaiTextService(
                    new MockHttpClient([
                        new MockResponse(json_encode(['output_text' => $workspaceSuggestionOutput], JSON_THROW_ON_ERROR)),
                    ]),
                    'configured-key',
                    'https://api.x.ai/v1',
                    'test-model',
                    30,
                ),
                $this->createPromptTemplateService(['workspace_suggestion_system' => 'workspace suggestion system prompt']),
            ),
            $transactionalMailer,
            new ToastTransferService(new WorkspaceWorkflowService(), $this->createMock(EntityManagerInterface::class)),
            new WorkspaceWorkflowService(),
            new JwtTokenService('unit-test-secret'),
            new AppUrlGenerator($this->createMock(UrlGeneratorInterface::class), 'https://toastit.test'),
            $writeEntityManager,
        );

        $result = $service->ingest(
            'toast+018f2e9a-9d9f-7f1f-8f7a-123456789abc@in.toastit.cc',
            'sender@example.com',
            $subject,
            $textBody,
            null,
            '<message-id>',
            null,
            '<references>',
        );

        self::assertInstanceOf(InboundEmailResult::class, $result);
        self::assertSame('toast_created', $result->getKind());
        self::assertSame($expected['title'], $result->getToast()?->getTitle());
        self::assertSame($expected['workspace'], $result->getToast()?->getWorkspace()->getName());
        self::assertSame($expected['owner'], $result->getToast()?->getOwner()?->getDisplayName());
        self::assertSame($expected['dueOn'], $result->getToast()?->getDueAt()?->format('Y-m-d'));
    }

    public static function scenarioProvider(): \Generator
    {
        yield 'full apply with high-confidence workspace and explicit datetime' => [
            'subject' => 'Réseau pro ce samedi 14h',
            'textBody' => 'Préparer une synthèse pour ce samedi à 14h avec les contacts professionnels.',
            'userPreferences' => ['reword' => true, 'assignee' => true, 'dueDate' => true, 'workspace' => true],
            'refinementOutput' => "TITLE: Préparer synthèse réseau\nASSIGNEE: Alex\nDUE_ON: 2026-04-11T14:00:00+02:00\nDESCRIPTION:\n## Contexte\n- Suivi réseau pro.\n\n## Action\n- Envoyer la synthèse.",
            'workspaceSuggestionOutput' => "WORKSPACE: Professional Network\nCONFIDENCE: 96\nREASON: Strong match with professional networking context.",
            'defaultWorkspaceName' => 'Default Workspace',
            'expected' => ['title' => 'Préparer synthèse réseau', 'workspace' => 'Professional Network', 'owner' => 'Alex', 'dueOn' => '2026-04-11'],
        ];

        yield 'low-confidence suggestion falls back to default workspace' => [
            'subject' => 'Evénement avec contacts',
            'textBody' => 'Organisation d’un événement avec des contacts mixtes perso/pro.',
            'userPreferences' => ['reword' => true, 'assignee' => true, 'dueDate' => true, 'workspace' => true],
            'refinementOutput' => "TITLE: Cadrer l'événement\nASSIGNEE: Alex\nDUE_ON: 2026-04-16\nDESCRIPTION:\n## Contexte\n- Besoin de cadrage.\n\n## Action\n- Confirmer les participants.",
            'workspaceSuggestionOutput' => "WORKSPACE: Professional Network\nCONFIDENCE: 52\nREASON: Ambiguous signal between personal and professional context.",
            'defaultWorkspaceName' => 'Default Workspace',
            'expected' => ['title' => "Cadrer l'événement", 'workspace' => 'Default Workspace', 'owner' => 'Alex', 'dueOn' => '2026-04-16'],
        ];

        yield 'low-confidence suggestion with no default keeps inbox' => [
            'subject' => 'Evénement hybride',
            'textBody' => 'Sujet mixte, pas clairement rattaché à un workspace.',
            'userPreferences' => ['reword' => true, 'assignee' => true, 'dueDate' => true, 'workspace' => true],
            'refinementOutput' => "TITLE: Clarifier le périmètre\nASSIGNEE: NONE\nDUE_ON: NONE\nDESCRIPTION:\n## Contexte\n- Besoin de qualification.",
            'workspaceSuggestionOutput' => "WORKSPACE: Professional Network\nCONFIDENCE: 40\nREASON: Unclear mapping to a single workspace.",
            'defaultWorkspaceName' => null,
            'expected' => ['title' => 'Clarifier le périmètre', 'workspace' => 'Inbox', 'owner' => null, 'dueOn' => null],
        ];

        yield 'preferences apply one-by-one (assignee and due date disabled)' => [
            'subject' => 'Suivi client demain',
            'textBody' => 'Préparer la note client pour demain matin.',
            'userPreferences' => ['reword' => true, 'assignee' => false, 'dueDate' => false, 'workspace' => true],
            'refinementOutput' => "TITLE: Préparer note client\nASSIGNEE: Alex\nDUE_ON: 2026-04-10\nDESCRIPTION:\n## Action\n- Préparer le document.",
            'workspaceSuggestionOutput' => "WORKSPACE: Professional Network\nCONFIDENCE: 95\nREASON: Strong match with client follow-up workstream.",
            'defaultWorkspaceName' => 'Default Workspace',
            'expected' => ['title' => 'Préparer note client', 'workspace' => 'Professional Network', 'owner' => null, 'dueOn' => null],
        ];
    }

    /**
     * @param array<string, string> $promptByCode
     */
    private function createPromptTemplateService(array $promptByCode): AiPromptTemplateService
    {
        $service = $this->createMock(AiPromptTemplateService::class);
        $service
            ->method('resolveSystemPrompt')
            ->willReturnCallback(static function (string $code, string $fallbackPrompt = '') use ($promptByCode): string {
                return $promptByCode[$code] ?? ('' !== trim($fallbackPrompt) ? $fallbackPrompt : 'system prompt');
            });

        return $service;
    }
}
