<?php

namespace App\Tests\Unit;

use App\Api\WorkspacePayloadBuilder;
use App\Entity\Toast;
use App\Entity\ToastComment;
use App\Entity\User;
use App\Entity\Vote;
use App\Entity\Workspace;
use App\Entity\WorkspaceNote;
use App\Entity\WorkspaceMember;
use App\Entity\WorkspaceNoteVersion;
use App\Profile\AvatarStorageService;
use App\Profile\AvatarUrlService;
use App\Meeting\MeetingAgendaBuilder;
use App\Profile\UserDateTimeFormatter;
use App\Repository\WorkspaceRepository;
use App\Tests\Support\ReflectionHelper;
use App\Workspace\InboundEmailAddressService;
use App\Workspace\WorkspaceNoteService;
use App\Workspace\WorkspaceWorkflowService;
use League\Flysystem\FilesystemOperator;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class WorkspacePayloadBuilderTest extends TestCase
{
    public function testBuildReturnsWorkspaceAndItemPayloads(): void
    {
        $currentUser = (new User())->setEmail('owner@example.com')->setFirstName('Owner');
        $member = (new User())->setEmail('member@example.com')->setFirstName('Member');
        ReflectionHelper::setId($currentUser, 1);
        ReflectionHelper::setId($member, 2);

        $workspace = (new Workspace())
            ->setName('Workspace')
            ->setOrganizer($currentUser)
            ->setIsDefault(true);
        ReflectionHelper::setId($workspace, 10);
        $workspace->addMembership((new WorkspaceMember())->setUser($currentUser)->setIsOwner(true));
        $workspace->addMembership((new WorkspaceMember())->setUser($member));

        $otherWorkspace = (new Workspace())
            ->setName('Other')
            ->setOrganizer($currentUser)
            ->setIsSoloWorkspace(true);
        ReflectionHelper::setId($otherWorkspace, 11);

        $activeItem = (new Toast())
            ->setWorkspace($workspace)
            ->setAuthor($currentUser)
            ->setTitle('Active')
            ->setDescription('Description')
            ->setOwner($member)
            ->setDueAt(new \DateTimeImmutable('2026-04-11'))
            ->setStatusChangedAt(new \DateTimeImmutable('2026-04-12'));
        ReflectionHelper::setId($activeItem, 100);

        $vote = (new Vote())->setItem($activeItem)->setUser($currentUser);
        $comment = (new ToastComment())->setToast($activeItem)->setAuthor($member)->setContent('Comment');
        $activeItem->addVote($vote)->addComment($comment);

        $followUpLater = (new Toast())
            ->setTitle('Later')
            ->setStatus(Toast::STATUS_PENDING)
            ->setOwner($member)
            ->setDueAt(new \DateTimeImmutable('2026-04-20'));
        $followUpEarlier = (new Toast())
            ->setTitle('Earlier')
            ->setStatus(Toast::STATUS_PENDING)
            ->setOwner(null)
            ->setDueAt(null);
        ReflectionHelper::setId($followUpLater, 202);
        ReflectionHelper::setId($followUpEarlier, 201);
        ReflectionHelper::setProperty($followUpLater, 'createdAt', new \DateTimeImmutable('2026-04-12 10:00:00'));
        ReflectionHelper::setProperty($followUpEarlier, 'createdAt', new \DateTimeImmutable('2026-04-12 09:00:00'));
        ReflectionHelper::setProperty($activeItem, 'followUpChildren', new \Doctrine\Common\Collections\ArrayCollection([$followUpLater, $followUpEarlier]));

        $resolvedItem = (new Toast())
            ->setWorkspace($workspace)
            ->setAuthor($member)
            ->setTitle('Resolved')
            ->setStatus(Toast::STATUS_TOASTED);
        ReflectionHelper::setId($resolvedItem, 101);

        $note = (new WorkspaceNote())
            ->setWorkspace($workspace)
            ->setAuthor($member)
            ->applySnapshot('Operating note', 'Body', true, new \DateTimeImmutable('2026-04-13 08:00:00'));
        ReflectionHelper::setId($note, 301);
        $noteVersion = (new WorkspaceNoteVersion())
            ->setAuthor($currentUser)
            ->setTitle('Operating note')
            ->setBody('Body')
            ->setIsImportant(true)
            ->setRecordedAt(new \DateTimeImmutable('2026-04-13 08:00:00'));
        ReflectionHelper::setId($noteVersion, 401);
        $note->addVersion($noteVersion);
        $workspace->addItem($activeItem)->addItem($resolvedItem);
        $workspace->addNote($note);

        $repository = $this->createMock(WorkspaceRepository::class);
        $repository
            ->expects(self::once())
            ->method('findForUser')
            ->with($currentUser)
            ->willReturn([$workspace, $otherWorkspace]);

        $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $urlGenerator->expects(self::never())->method('generate');
        $filesystem = $this->createMock(FilesystemOperator::class);
        $avatarUrl = new AvatarUrlService($urlGenerator, new AvatarStorageService($filesystem, sys_get_temp_dir()));

        $builder = new WorkspacePayloadBuilder(
            new MeetingAgendaBuilder(),
            new WorkspaceWorkflowService(),
            $repository,
            $avatarUrl,
            new UserDateTimeFormatter(),
            new InboundEmailAddressService('inbound.toastit.test'),
            new WorkspaceNoteService($this->createMock(\Doctrine\ORM\EntityManagerInterface::class)),
            $urlGenerator,
        );
        $payload = $builder->build($workspace, $currentUser);

        self::assertSame(1, $payload['currentUser']['id']);
        self::assertSame('Workspace', $payload['workspace']['name']);
        self::assertTrue($payload['workspace']['currentUserIsOwner']);
        self::assertSame(1, $payload['workspace']['ownerCount']);
        self::assertSame([[
            'id' => 11,
            'name' => 'Other',
            'isSoloWorkspace' => true,
            'isInboxWorkspace' => false,
        ]], $payload['otherWorkspaces']);
        self::assertCount(2, $payload['memberships']);
        self::assertCount(2, $payload['participants']);
        self::assertSame(100, $payload['agendaItems'][0]['id']);
        self::assertSame(1, $payload['agendaItems'][0]['voteCount']);
        self::assertTrue($payload['agendaItems'][0]['currentUserHasVoted']);
        self::assertSame('Member', $payload['agendaItems'][0]['ownerName']);
        self::assertSame('11/04/2026', $payload['agendaItems'][0]['dueOnDisplay']);
        self::assertSame('12/04/2026', $payload['agendaItems'][0]['statusChangedAtDisplay']);
        self::assertSame('Earlier', $payload['agendaItems'][0]['followUpItems'][0]['title']);
        self::assertSame('Later', $payload['agendaItems'][0]['followUpItems'][1]['title']);
        self::assertSame('Comment', $payload['agendaItems'][0]['comments'][0]['content']);
        self::assertSame(301, $payload['notes'][0]['id']);
        self::assertTrue($payload['notes'][0]['currentUserCanDelete']);
        self::assertSame(401, $payload['notes'][0]['versions'][0]['id']);
        self::assertSame(101, $payload['resolvedItems'][0]['id']);
    }
}
