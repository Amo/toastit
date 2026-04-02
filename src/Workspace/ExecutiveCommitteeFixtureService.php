<?php

namespace App\Workspace;

use App\Entity\Toast;
use App\Entity\ToastComment;
use App\Entity\ToastingSession;
use App\Entity\User;
use App\Entity\Vote;
use App\Entity\Workspace;
use App\Entity\WorkspaceMember;
use App\Repository\UserRepository;
use App\Repository\WorkspaceRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class ExecutiveCommitteeFixtureService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly WorkspaceRepository $workspaceRepository,
        private readonly UserRepository $userRepository,
    ) {
    }

    /**
     * @return array{members: int, items: int, comments: int, votes: int, sessions: int}
     */
    public function seed(int $workspaceId = 2): array
    {
        $workspace = $this->workspaceRepository->find($workspaceId);

        if (!$workspace instanceof Workspace) {
            throw new NotFoundHttpException(sprintf('Workspace %d was not found.', $workspaceId));
        }

        $workspace
            ->setName('Braincube ExCo')
            ->setIsSoloWorkspace(false)
            ->setDefaultDuePreset(Workspace::DEFAULT_DUE_NEXT_WEEK);

        $members = $this->resolveMembers($workspace);
        $this->entityManager->flush();

        $connection = $this->entityManager->getConnection();
        $connection->beginTransaction();

        try {
            $connection->executeStatement('DELETE FROM toast_comment WHERE toast_id IN (SELECT id FROM parking_lot_item WHERE team_id = ?)', [$workspaceId]);
            $connection->executeStatement('DELETE FROM vote WHERE item_id IN (SELECT id FROM parking_lot_item WHERE team_id = ?)', [$workspaceId]);
            $connection->executeStatement('DELETE FROM parking_lot_item WHERE team_id = ?', [$workspaceId]);
            $connection->executeStatement('DELETE FROM toasting_session WHERE workspace_id = ?', [$workspaceId]);
            $connection->executeStatement('DELETE FROM team_member WHERE team_id = ?', [$workspaceId]);
            $connection->commit();
        } catch (\Throwable $exception) {
            $connection->rollBack();

            throw $exception;
        }

        $this->entityManager->clear();

        $workspace = $this->workspaceRepository->find($workspaceId);

        if (!$workspace instanceof Workspace) {
            throw new NotFoundHttpException(sprintf('Workspace %d was not found after reset.', $workspaceId));
        }

        $managedMembers = array_map(fn (array $member): array => [
            ...$member,
            'user' => $this->userRepository->findOneByNormalizedEmail($member['email']),
        ], $members);

        $managedMembers = array_values(array_filter($managedMembers, static fn (array $member): bool => $member['user'] instanceof User));
        $counts = [
            'members' => 0,
            'items' => 0,
            'comments' => 0,
            'votes' => 0,
            'sessions' => 0,
        ];

        $this->createMemberships($workspace, $managedMembers, $counts);
        $this->createTimeline($workspace, $managedMembers, $counts);

        $this->entityManager->flush();

        return $counts;
    }

    /**
     * @return list<array{email: string, firstName: string, lastName: string, title: string, owner: bool}>
     */
    private function resolveMembers(Workspace $workspace): array
    {
        $organizer = $workspace->getOrganizer();

        if (null === $organizer->getFirstName()) {
            $organizer->setFirstName('Amaury');
        }

        if (null === $organizer->getLastName()) {
            $organizer->setLastName('Leroux de Lens');
        }

        $profiles = [
            [
                'email' => $organizer->getEmail(),
                'firstName' => $organizer->getFirstName() ?? 'Amaury',
                'lastName' => $organizer->getLastName() ?? 'Leroux de Lens',
                'title' => 'Executive Operations',
                'owner' => true,
            ],
            ['email' => 'claire.martin@braincube.com', 'firstName' => 'Claire', 'lastName' => 'Martin', 'title' => 'Chief Executive Officer', 'owner' => true],
            ['email' => 'julien.bernard@braincube.com', 'firstName' => 'Julien', 'lastName' => 'Bernard', 'title' => 'Chief Revenue Officer', 'owner' => true],
            ['email' => 'sophie.lambert@braincube.com', 'firstName' => 'Sophie', 'lastName' => 'Lambert', 'title' => 'Chief Product Officer', 'owner' => true],
            ['email' => 'thomas.renaud@braincube.com', 'firstName' => 'Thomas', 'lastName' => 'Renaud', 'title' => 'Chief Technology Officer', 'owner' => true],
            ['email' => 'camille.perrin@braincube.com', 'firstName' => 'Camille', 'lastName' => 'Perrin', 'title' => 'Chief Customer Officer', 'owner' => false],
            ['email' => 'marc.dubois@braincube.com', 'firstName' => 'Marc', 'lastName' => 'Dubois', 'title' => 'Chief Financial Officer', 'owner' => false],
            ['email' => 'lea.morel@braincube.com', 'firstName' => 'Lea', 'lastName' => 'Morel', 'title' => 'VP People', 'owner' => false],
        ];

        foreach ($profiles as $profile) {
            $user = $this->userRepository->findOneByNormalizedEmail($profile['email']) ?? (new User())->setEmail($profile['email']);
            $user
                ->setFirstName($profile['firstName'])
                ->setLastName($profile['lastName']);

            $this->entityManager->persist($user);
        }

        return $profiles;
    }

    /**
     * @param list<array{email: string, firstName: string, lastName: string, title: string, owner: bool, user: User}> $members
     * @param array{members: int, items: int, comments: int, votes: int, sessions: int} $counts
     */
    private function createMemberships(Workspace $workspace, array $members, array &$counts): void
    {
        $createdAt = new \DateTimeImmutable('-6 months');

        foreach ($members as $index => $member) {
            $membership = (new WorkspaceMember())
                ->setWorkspace($workspace)
                ->setUser($member['user'])
                ->setIsOwner($member['owner'])
                ->setDisplayOrder($index + 1);

            $this->setDateTimeProperty($membership, 'createdAt', $createdAt->modify(sprintf('+%d days', $index)));
            $this->entityManager->persist($membership);
            $workspace->addMembership($membership);
            ++$counts['members'];
        }
    }

    /**
     * @param list<array{email: string, firstName: string, lastName: string, title: string, owner: bool, user: User}> $members
     * @param array{members: int, items: int, comments: int, votes: int, sessions: int} $counts
     */
    private function createTimeline(Workspace $workspace, array $members, array &$counts): void
    {
        $topics = [
            'Factory Copilot rollout readiness',
            'Strategic expansion in Germany',
            'Executive sponsor plan for top 20 accounts',
            'Board package and KPI narrative',
            'Cloud margin and hosting cost guardrails',
            'Customer value proof for manufacturing AI',
            'Partner strategy for system integrators',
            'Pricing guardrails for multi-site deals',
            'Product adoption on energy use cases',
            'Hiring plan for customer success leadership',
            'Pipeline risk review for Q4',
            'Cash discipline and investment pacing',
            'Delivery capacity for enterprise onboarding',
            'Roadmap trade-offs for industrial copilots',
            'Security and compliance execution',
            'Executive escalation on churn prevention',
            'AI governance posture for large customers',
            'Services attach strategy',
            'Reference customer program',
            'Internal decision cadence improvements',
            'North America expansion posture',
            'Customer health coverage model',
            'Data platform reliability priorities',
            'ExCo operating model adjustments',
            'Strategic M&A scanning',
            'Annual planning preparation',
        ];

        $commentOpeners = [
            'We need a decision with a visible owner before Friday.',
            'Please arrive with one recommendation and one explicit risk.',
            'We should validate impact on strategic accounts before committing.',
            'This needs a tighter success metric and a named sponsor.',
            'Let us document the decision rule, not only the action.',
        ];

        $pastWeeks = 20;
        $futureWeeks = 6;
        $startMonday = new \DateTimeImmutable('monday this week 09:00');

        for ($offset = -$pastWeeks; $offset <= $futureWeeks; ++$offset) {
            $meetingAt = $startMonday->modify(sprintf('%+d weeks', $offset));
            $topic = $topics[($offset + $pastWeeks) % count($topics)];
            $owner = $members[($offset + $pastWeeks) % count($members)]['user'];
            $author = $members[($offset + $pastWeeks + 1) % count($members)]['user'];
            $secondaryAuthor = $members[($offset + $pastWeeks + 2) % count($members)]['user'];

            if ($offset <= 0) {
                $this->createPastWeekFixtures($workspace, $meetingAt, $topic, $owner, $author, $secondaryAuthor, $members, $counts, $offset);
                continue;
            }

            $this->createFutureWeekFixtures($workspace, $meetingAt, $topic, $owner, $author, $secondaryAuthor, $members, $counts, $offset);
        }
    }

    /**
     * @param list<array{email: string, firstName: string, lastName: string, title: string, owner: bool, user: User}> $members
     * @param array{members: int, items: int, comments: int, votes: int, sessions: int} $counts
     */
    private function createPastWeekFixtures(Workspace $workspace, \DateTimeImmutable $meetingAt, string $topic, User $owner, User $author, User $secondaryAuthor, array $members, array &$counts, int $offset): void
    {
        $session = (new ToastingSession())
            ->setWorkspace($workspace)
            ->setStartedBy($author)
            ->setEndedBy($author)
            ->setStartedAt($meetingAt)
            ->setEndedAt($meetingAt->modify('+75 minutes'));
        $this->entityManager->persist($session);
        ++$counts['sessions'];

        $decisionToast = $this->createToast(
            workspace: $workspace,
            title: sprintf('ExCo - %s', $topic),
            author: $author,
            owner: $owner,
            createdAt: $meetingAt->modify('-2 days'),
            dueAt: $meetingAt->modify('+14 days'),
            description: sprintf("Context for weekly ExCo review on %s.\n\nExpected output:\n- one strategic decision\n- one owner\n- one measurable checkpoint\n\nFocus: Braincube executive committee support around %s.", $meetingAt->format('Y-m-d'), strtolower($topic)),
            discussionStatus: Toast::DISCUSSION_TREATED,
            discussionNotes: sprintf("Decision taken during the weekly executive committee: go forward on %s with a tighter weekly checkpoint and one executive owner. Team aligned on expected customer impact, staffing implications, and communication cadence.", strtolower($topic)),
            statusChangedAt: $meetingAt,
            isBoosted: $offset % 4 === 0
        );
        $this->entityManager->persist($decisionToast);
        ++$counts['items'];

        $this->createComment($decisionToast, $author, $meetingAt->modify('-2 days +2 hours'), sprintf('Pre-read attached for %s. I need a firm go/no-go and named owner.', strtolower($topic)), $counts);
        $this->createComment($decisionToast, $secondaryAuthor, $meetingAt->modify('-1 day +1 hour'), sprintf('My recommendation: keep scope narrow, prove value in two lighthouse accounts, and review execution next week. %s', $this->pickCommentOpener($offset)), $counts);

        $followUpDueAt = $meetingAt->modify('+14 days');
        $followUpResolved = $offset <= -4;
        $followUpToast = $this->createToast(
            workspace: $workspace,
            title: sprintf('Follow-up - %s', $topic),
            author: $author,
            owner: $owner,
            createdAt: $meetingAt->modify('+1 day'),
            dueAt: $followUpDueAt,
            description: sprintf('Execution follow-up created from the ExCo decision on %s.', $topic),
            discussionStatus: $followUpResolved ? Toast::DISCUSSION_TREATED : Toast::DISCUSSION_PENDING,
            discussionNotes: $followUpResolved ? 'Follow-up delivered and reviewed in ExCo.' : null,
            previousItem: $decisionToast,
            statusChangedAt: $followUpResolved ? $meetingAt->modify('+15 days') : null,
            isBoosted: !$followUpResolved && $offset >= -2
        );
        $this->entityManager->persist($followUpToast);
        ++$counts['items'];

        $this->createComment($followUpToast, $owner, $meetingAt->modify('+2 days'), sprintf('Action plan posted. Weekly checkpoint starts now for %s.', strtolower($topic)), $counts);

        $decisionToast->setFollowUpItems([
            [
                'title' => $followUpToast->getTitle(),
                'ownerId' => $owner->getId(),
                'dueOn' => $followUpDueAt->format('Y-m-d'),
            ],
        ]);

        if ($offset % 3 === 0) {
            $vetoedToast = $this->createToast(
                workspace: $workspace,
                title: sprintf('Alternative option - %s', $topic),
                author: $secondaryAuthor,
                owner: $owner,
                createdAt: $meetingAt->modify('-1 day'),
                dueAt: $meetingAt->modify('+7 days'),
                description: 'Alternative option recorded during ExCo and deliberately declined after discussion.',
                status: Toast::STATUS_VETOED,
                discussionStatus: Toast::DISCUSSION_PENDING,
                discussionNotes: 'Declined to avoid dilution of focus and duplicate execution tracks.',
                statusChangedAt: $meetingAt
            );
            $this->entityManager->persist($vetoedToast);
            ++$counts['items'];
        }

        if ($offset >= -3) {
            $openExecutionToast = $this->createToast(
                workspace: $workspace,
                title: sprintf('Open action - %s', $topic),
                author: $secondaryAuthor,
                owner: $members[($offset + count($members) + 3) % count($members)]['user'],
                createdAt: $meetingAt->modify('+2 days'),
                dueAt: $meetingAt->modify('+10 days'),
                description: 'Cross-functional execution item still being tracked in the active list.',
                discussionStatus: Toast::DISCUSSION_PENDING,
                isBoosted: $offset >= -1
            );
            $this->entityManager->persist($openExecutionToast);
            ++$counts['items'];

            foreach (array_slice($members, 0, min(3, count($members))) as $voteMember) {
                $this->createVote($openExecutionToast, $voteMember['user'], $meetingAt->modify('+3 days'), $counts);
            }
        }
    }

    /**
     * @param list<array{email: string, firstName: string, lastName: string, title: string, owner: bool, user: User}> $members
     * @param array{members: int, items: int, comments: int, votes: int, sessions: int} $counts
     */
    private function createFutureWeekFixtures(Workspace $workspace, \DateTimeImmutable $meetingAt, string $topic, User $owner, User $author, User $secondaryAuthor, array $members, array &$counts, int $offset): void
    {
        $agendaToast = $this->createToast(
            workspace: $workspace,
            title: sprintf('Upcoming ExCo - %s', $topic),
            author: $author,
            owner: $owner,
            createdAt: $meetingAt->modify('-5 days'),
            dueAt: $meetingAt->modify('+7 days'),
            description: sprintf("Preparation item for the upcoming executive committee.\n\nExpected decision:\n- align on scope\n- confirm owner\n- lock the next milestone\n\nTopic: %s.", $topic),
            discussionStatus: Toast::DISCUSSION_PENDING,
            isBoosted: $offset <= 2
        );
        $this->entityManager->persist($agendaToast);
        ++$counts['items'];

        $this->createComment($agendaToast, $author, $meetingAt->modify('-4 days'), sprintf('Please prepare your recommendation for %s.', strtolower($topic)), $counts);
        $this->createComment($agendaToast, $secondaryAuthor, $meetingAt->modify('-3 days'), sprintf('We also need the downside scenario and one staffing implication. %s', $this->pickCommentOpener($offset)), $counts);

        foreach (array_slice($members, 0, min(2 + ($offset % 3), count($members))) as $voteMember) {
            $this->createVote($agendaToast, $voteMember['user'], $meetingAt->modify('-2 days'), $counts);
        }
    }

    private function createToast(
        Workspace $workspace,
        string $title,
        User $author,
        ?User $owner,
        \DateTimeImmutable $createdAt,
        ?\DateTimeImmutable $dueAt,
        ?string $description,
        string $discussionStatus,
        ?string $discussionNotes = null,
        string $status = Toast::STATUS_OPEN,
        ?\DateTimeImmutable $statusChangedAt = null,
        ?Toast $previousItem = null,
        bool $isBoosted = false,
    ): Toast {
        $toast = (new Toast())
            ->setWorkspace($workspace)
            ->setAuthor($author)
            ->setTitle($title)
            ->setDescription($description)
            ->setOwner($owner)
            ->setStatus($status)
            ->setIsBoosted($isBoosted)
            ->setDiscussionStatus($discussionStatus)
            ->setDiscussionNotes($discussionNotes)
            ->setDueAt($dueAt)
            ->setStatusChangedAt($statusChangedAt)
            ->setPreviousItem($previousItem);

        $this->setDateTimeProperty($toast, 'createdAt', $createdAt);

        return $toast;
    }

    /**
     * @param array{members: int, items: int, comments: int, votes: int, sessions: int} $counts
     */
    private function createComment(Toast $toast, User $author, \DateTimeImmutable $createdAt, string $content, array &$counts): void
    {
        $comment = (new ToastComment())
            ->setToast($toast)
            ->setAuthor($author)
            ->setContent($content);

        $this->setDateTimeProperty($comment, 'createdAt', $createdAt);
        $toast->addComment($comment);
        $this->entityManager->persist($comment);
        ++$counts['comments'];
    }

    /**
     * @param array{members: int, items: int, comments: int, votes: int, sessions: int} $counts
     */
    private function createVote(Toast $toast, User $user, \DateTimeImmutable $createdAt, array &$counts): void
    {
        $vote = (new Vote())
            ->setItem($toast)
            ->setUser($user);

        $this->setDateTimeProperty($vote, 'createdAt', $createdAt);
        $toast->addVote($vote);
        $this->entityManager->persist($vote);
        ++$counts['votes'];
    }

    private function pickCommentOpener(int $seed): string
    {
        $commentOpeners = [
            'Please keep the narrative board-ready and execution-ready.',
            'Let us be explicit about risk, owner, and expected business impact.',
            'This should stay tight enough to be reviewed every week.',
            'I would rather reduce scope than accept vague accountability.',
            'Make sure the decision can be tracked with one visible KPI.',
        ];

        return $commentOpeners[abs($seed) % count($commentOpeners)];
    }

    private function setDateTimeProperty(object $object, string $property, \DateTimeImmutable $value): void
    {
        $reflection = new \ReflectionObject($object);

        while (false !== $reflection) {
            if ($reflection->hasProperty($property)) {
                $reflectionProperty = $reflection->getProperty($property);
                $reflectionProperty->setAccessible(true);
                $reflectionProperty->setValue($object, $value);

                return;
            }

            $reflection = $reflection->getParentClass() ?: false;
        }
    }
}
