<?php

namespace App\Tests\Unit;

use App\Entity\ApiRefreshToken;
use App\Entity\LoginChallenge;
use App\Entity\Toast;
use App\Entity\ToastComment;
use App\Entity\ToastingSession;
use App\Entity\User;
use App\Entity\Vote;
use App\Entity\Workspace;
use App\Entity\WorkspaceNote;
use App\Entity\WorkspaceMember;
use App\Entity\WorkspaceNoteVersion;
use App\Tests\Support\ReflectionHelper;
use Doctrine\Common\Collections\ArrayCollection;
use PHPUnit\Framework\TestCase;

final class EntityBehaviorTest extends TestCase
{
    public function testUserNormalizesNamesRolesAndIdentityHelpers(): void
    {
        $user = (new User())
            ->setEmail('  jane@example.com ')
            ->setFirstName('  Jane ')
            ->setLastName(' Doe  ')
            ->setRoles(['role_root', 'ROLE_ROOT', '', 'role_editor']);

        self::assertSame('Jane', $user->getFirstName());
        self::assertSame('Doe', $user->getLastName());
        self::assertSame('Jane Doe', $user->getDisplayName());
        self::assertSame('JD', $user->getInitials());
        self::assertSame(['ROLE_ROOT', 'ROLE_EDITOR', 'ROLE_USER'], $user->getRoles());
        self::assertTrue($user->isRoot());
        self::assertStringContainsString(md5('jane@example.com'), $user->getGravatarUrl());

        $user->removeRole('role_root');

        self::assertFalse($user->isRoot());

        $user
            ->setFirstName(null)
            ->setLastName(null)
            ->setPinHash('hash')
            ->setAvatarPath(' avatar.png ');

        self::assertSame('  jane@example.com ', $user->getDisplayName());
        self::assertSame('  ', $user->getInitials());
        self::assertTrue($user->hasPin());
        self::assertSame('avatar.png', $user->getAvatarPath());
        self::assertSame('  jane@example.com ', $user->getUserIdentifier());
        self::assertSame('hash', $user->getPassword());

        $user->anonymize();

        self::assertNull($user->getAvatarPath());
    }

    public function testWorkspaceHandlesMembershipsMeetingModeAndItems(): void
    {
        $organizer = (new User())->setEmail('owner@example.com');
        $ownerMemberUser = (new User())->setEmail('member@example.com');
        $guestUser = (new User())->setEmail('guest@example.com');
        ReflectionHelper::setId($organizer, 1);
        ReflectionHelper::setId($ownerMemberUser, 2);
        ReflectionHelper::setId($guestUser, 3);

        $workspace = (new Workspace())
            ->setName('Ops')
            ->setOrganizer($organizer)
            ->setDefaultDuePreset('invalid')
            ->setPermalinkBackgroundUrl('  https://example.com/bg.jpg  ');

        $ownerMembership = (new WorkspaceMember())
            ->setUser($ownerMemberUser)
            ->setIsOwner(true);
        $guestMembership = (new WorkspaceMember())
            ->setUser($guestUser)
            ->setIsOwner(false);

        $workspace
            ->addMembership($ownerMembership)
            ->addMembership($guestMembership)
            ->addMembership($ownerMembership);

        self::assertSame(Workspace::DEFAULT_DUE_NEXT_WEEK, $workspace->getDefaultDuePreset());
        self::assertSame('https://example.com/bg.jpg', $workspace->getPermalinkBackgroundUrl());
        self::assertCount(2, $workspace->getMemberships());
        self::assertSame($workspace, $ownerMembership->getWorkspace());
        self::assertTrue($workspace->isOwnedBy($organizer));
        self::assertTrue($workspace->isOwnedBy($ownerMemberUser));
        self::assertFalse($workspace->isOwnedBy($guestUser));
        self::assertSame(1, $workspace->getOwnerCount());

        $startedAt = new \DateTimeImmutable('2026-04-02 09:00:00');
        $endedAt = new \DateTimeImmutable('2026-04-02 10:00:00');
        $workspace->startMeetingMode($organizer, $startedAt);

        self::assertTrue($workspace->isMeetingLive());
        self::assertSame($startedAt, $workspace->getMeetingStartedAt());
        self::assertInstanceOf(ToastingSession::class, $workspace->getActiveToastingSession());

        $activeSession = $workspace->getActiveToastingSession();
        self::assertInstanceOf(ToastingSession::class, $activeSession);
        self::assertSame($organizer, $activeSession->getStartedBy());

        $workspace->startMeetingMode($guestUser, new \DateTimeImmutable('2026-04-02 11:00:00'));

        self::assertCount(1, $workspace->getToastingSessions());

        $workspace->stopMeetingMode($guestUser, $endedAt);

        self::assertFalse($workspace->isMeetingLive());
        self::assertSame($endedAt, $workspace->getMeetingEndedAt());
        self::assertNull($workspace->getActiveToastingSession());
        self::assertSame($guestUser, $activeSession->getEndedBy());
        self::assertSame($endedAt, $activeSession->getEndedAt());

        $toast = (new Toast())->setTitle('Item');
        $workspace->addItem($toast);

        self::assertCount(1, $workspace->getItems());
        self::assertSame($workspace, $toast->getWorkspace());

        $workspace->setMeetingMode(Workspace::MEETING_MODE_LIVE);
        $workspace->setIsSoloWorkspace(true);

        self::assertTrue($workspace->isSoloWorkspace());
        self::assertSame(Workspace::MEETING_MODE_IDLE, $workspace->getMeetingMode());
        self::assertNull($workspace->getMeetingStartedAt());
        self::assertNull($workspace->getMeetingEndedAt());

        $workspace->setIsInboxWorkspace(true);
        $workspace->setIsSoloWorkspace(false);
        $workspace->setMeetingMode(Workspace::MEETING_MODE_LIVE);

        self::assertTrue($workspace->isInboxWorkspace());
        self::assertTrue($workspace->isSoloWorkspace());
        self::assertSame(Workspace::MEETING_MODE_IDLE, $workspace->getMeetingMode());

        $note = (new WorkspaceNote())
            ->setAuthor($organizer)
            ->applySnapshot('  Team recap  ', "  ## Decisions\n\n- Ship  ", true, new \DateTimeImmutable('2026-04-03 08:30:00'));
        $version = (new WorkspaceNoteVersion())
            ->setAuthor($guestUser)
            ->setTitle('Team recap')
            ->setBody("## Decisions\n\n- Ship")
            ->setIsImportant(true);

        $workspace->addNote($note);
        $note->addVersion($version);

        self::assertCount(1, $workspace->getNotes());
        self::assertSame($workspace, $note->getWorkspace());
        self::assertTrue($note->isImportant());
        self::assertSame('Team recap', $note->getTitle());
        self::assertSame("## Decisions\n\n- Ship", $note->getBody());
        self::assertTrue($note->matchesSnapshot('Team recap', "## Decisions\n\n- Ship", true));
        self::assertCount(1, $note->getVersions());
        self::assertSame($note, $version->getNote());
    }

    public function testToastSanitizesFollowUpsAndTracksVotesCommentsAndFlags(): void
    {
        $workspace = (new Workspace())->setName('Ops')->setOrganizer((new User())->setEmail('owner@example.com'));
        $author = (new User())->setEmail('author@example.com');
        $owner = (new User())->setEmail('followup@example.com');
        ReflectionHelper::setId($author, 10);
        ReflectionHelper::setId($owner, 11);

        $toast = (new Toast())
            ->setWorkspace($workspace)
            ->setAuthor($author)
            ->setTitle('Main')
            ->setDescription('Desc')
            ->setFollowUp('Legacy')
            ->setFollowUpItems([
                ['title' => ' Follow-up ', 'ownerId' => '11', 'dueOn' => '2026-04-05'],
                ['title' => '', 'ownerId' => 9, 'dueOn' => '2026-04-06'],
                'invalid',
                ['title' => 'No owner', 'ownerId' => 'abc', 'dueOn' => ''],
            ])
            ->setOwner($owner)
            ->setDueAt(new \DateTimeImmutable('2026-04-05'))
            ->setStatusChangedAt(new \DateTimeImmutable('2026-04-06'));

        self::assertTrue($toast->isNew());
        self::assertFalse($toast->isVetoed());
        self::assertFalse($toast->isReady());
        self::assertFalse($toast->isToasted());
        self::assertSame([
            ['title' => 'Follow-up', 'ownerId' => 11, 'dueOn' => '2026-04-05'],
            ['title' => 'No owner', 'ownerId' => null, 'dueOn' => null],
        ], $toast->getFollowUpItems());

        $toast
            ->setStatus(Toast::STATUS_DISCARDED)
            ->setIsBoosted(true)
            ->setBoostRank(3)
            ->setIsBoosted(false);

        self::assertTrue($toast->isVetoed());
        self::assertFalse($toast->isToasted());
        self::assertFalse($toast->isNew());
        self::assertNull($toast->getBoostRank());

        $toast->setStatus(Toast::STATUS_TOASTED);
        self::assertTrue($toast->isToasted());

        $toast
            ->setStatus(Toast::STATUS_PENDING)
            ->setStatus(Toast::STATUS_READY)
            ->setAiRefinementPending(true);

        self::assertTrue($toast->isReady());
        self::assertTrue($toast->isNew());
        self::assertTrue($toast->isAiRefinementPending());

        $vote = (new Vote())->setItem($toast)->setUser($author);
        $toast->addVote($vote)->addVote($vote);

        self::assertCount(1, $toast->getVotes());
        self::assertSame(1, $toast->getVoteCount());

        $toast->removeVote($vote);
        self::assertSame(0, $toast->getVoteCount());

        $comment = (new ToastComment())->setToast($toast)->setAuthor($author)->setContent('Hello');
        $toast->addComment($comment)->addComment($comment);

        self::assertCount(1, $toast->getComments());
        self::assertSame($toast, $comment->getToast());
        self::assertSame($author, $comment->getAuthor());
        self::assertSame('Hello', $comment->getContent());
    }

    public function testTokenAndChallengeEntitiesExposeLifecyclePredicates(): void
    {
        $user = (new User())->setEmail('user@example.com');

        $refreshToken = (new ApiRefreshToken())
            ->setUser($user)
            ->setTokenHash('hash')
            ->setLastUsedAt(new \DateTimeImmutable('2026-04-02 08:00:00'))
            ->setExpiresAt(new \DateTimeImmutable('2026-04-09 08:00:00'));

        self::assertFalse($refreshToken->isExpired(new \DateTimeImmutable('2026-04-08 08:00:00')));
        self::assertTrue($refreshToken->isExpired(new \DateTimeImmutable('2026-04-09 08:00:00')));

        $challenge = (new LoginChallenge())
            ->setUser($user)
            ->setSelector('selector')
            ->setCode('ABC123')
            ->setTokenHash('hash')
            ->setPurpose(LoginChallenge::PURPOSE_RESET_PIN)
            ->setExpiresAt(new \DateTimeImmutable('2026-04-02 09:00:00'));

        self::assertFalse($challenge->isUsed());
        self::assertFalse($challenge->isExpired(new \DateTimeImmutable('2026-04-02 08:59:59')));

        $challenge->setUsedAt(new \DateTimeImmutable('2026-04-02 08:30:00'));

        self::assertTrue($challenge->isUsed());
        self::assertTrue($challenge->isExpired(new \DateTimeImmutable('2026-04-02 09:00:00')));
    }

    public function testSecondaryEntitiesExposeStateAndRelations(): void
    {
        $workspace = (new Workspace())->setName('Ops')->setOrganizer((new User())->setEmail('owner@example.com'));
        $user = (new User())->setEmail('user@example.com');
        $toast = (new Toast())->setWorkspace($workspace)->setAuthor($user)->setTitle('Main');

        $membership = (new WorkspaceMember())
            ->setWorkspace($workspace)
            ->setUser($user)
            ->setDisplayOrder(4)
            ->setIsOwner(true);

        self::assertSame($workspace, $membership->getWorkspace());
        self::assertSame($user, $membership->getUser());
        self::assertSame(4, $membership->getDisplayOrder());
        self::assertTrue($membership->isOwner());

        $vote = (new Vote())->setItem($toast)->setUser($user);
        self::assertSame($toast, $vote->getItem());
        self::assertSame($user, $vote->getUser());

        $session = (new ToastingSession())
            ->setWorkspace($workspace)
            ->setStartedBy($user)
            ->setStartedAt(new \DateTimeImmutable('2026-04-02 09:00:00'));

        self::assertTrue($session->isActive());

        $session
            ->setEndedBy($user)
            ->setEndedAt(new \DateTimeImmutable('2026-04-02 10:00:00'));

        self::assertFalse($session->isActive());
        self::assertSame($workspace, $session->getWorkspace());
        self::assertSame($user, $session->getStartedBy());
        self::assertSame($user, $session->getEndedBy());
    }
}
