<?php

namespace App\Tests\Unit;

use App\Entity\User;
use App\Mailer\TransactionalMailer;
use App\Meeting\XaiTextService;
use App\Repository\ToastRepository;
use App\Repository\UserRepository;
use App\Workspace\InboundEmailAddressService;
use App\Workspace\InboundEmailService;
use App\Workspace\InboxWorkspaceService;
use App\Workspace\TodoDigestService;
use App\Workspace\ToastCreationService;
use App\Repository\WorkspaceMemberRepository;
use App\Repository\WorkspaceRepository;
use Doctrine\ORM\EntityManagerInterface;
use League\CommonMark\CommonMarkConverter;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

final class InboundEmailServiceTest extends TestCase
{
    public function testTodoSubjectSendsDigestInsteadOfCreatingToast(): void
    {
        $user = (new User())->setEmail('owner@example.com');

        $userRepository = $this->createMock(UserRepository::class);
        $userRepository
            ->expects(self::once())
            ->method('findOneByNormalizedEmail')
            ->with('owner@example.com')
            ->willReturn($user);

        $inboxWorkspace = new InboxWorkspaceService(
            $this->createMock(WorkspaceRepository::class),
            $this->createMock(WorkspaceMemberRepository::class),
            $this->createMock(EntityManagerInterface::class),
        );

        $toastEntityManager = $this->createMock(EntityManagerInterface::class);
        $toastEntityManager->expects(self::never())->method('persist');
        $toastCreation = new ToastCreationService($toastEntityManager);

        $toastRepository = $this->createMock(ToastRepository::class);
        $toastRepository
            ->expects(self::once())
            ->method('findAssignedActiveForUser')
            ->with($user)
            ->willReturn([]);

        $mailerTransport = $this->createMock(MailerInterface::class);
        $mailerTransport
            ->expects(self::once())
            ->method('send')
            ->with(self::callback(function (Email $email): bool {
                self::assertSame('Re: todo', $email->getSubject());

                return true;
            }));

        $todoDigest = new TodoDigestService(
            $toastRepository,
            new XaiTextService(new MockHttpClient(), '', 'https://api.x.ai/v1', 'test-model', 30),
            new TransactionalMailer(
                $mailerTransport,
                new Environment(new FilesystemLoader(dirname(__DIR__, 2).'/templates')),
                new CommonMarkConverter(),
                'no-reply@toastit.local',
            ),
        );

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::never())->method('flush');

        $service = new InboundEmailService(
            new InboundEmailAddressService('inbound.toastit.test'),
            $userRepository,
            $inboxWorkspace,
            $toastCreation,
            $todoDigest,
            $entityManager,
        );

        $result = $service->ingest(
            'toast+b3duZXJAZXhhbXBsZS5jb20@inbound.toastit.test',
            'sender@example.com',
            ' todo ',
            'body',
        );

        self::assertNotNull($result);
        self::assertSame('todo_digest_sent', $result->getKind());
        self::assertNull($result->getToast());
    }
}
