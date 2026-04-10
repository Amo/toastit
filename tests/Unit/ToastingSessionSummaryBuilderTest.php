<?php

namespace App\Tests\Unit;

use App\Entity\Toast;
use App\Entity\ToastComment;
use App\Entity\User;
use App\Entity\Workspace;
use App\Meeting\ToastingSessionSummaryBuilder;
use App\Workspace\WorkspaceWorkflowService;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

final class ToastingSessionSummaryBuilderTest extends TestCase
{
    public function testBuildPromptIncludesDecisionsFollowUpsAndSessionComments(): void
    {
        $workspace = (new Workspace())
            ->setName('Executive committee')
            ->setOrganizer($this->createUser('owner@example.com', 'Owner'));

        $workspace->startMeetingMode($workspace->getOrganizer(), new \DateTimeImmutable('2026-04-01 09:00:00'));
        $workspace->stopMeetingMode($workspace->getOrganizer(), new \DateTimeImmutable('2026-04-01 10:00:00'));
        $session = $workspace->getToastingSessions()->first();

        self::assertInstanceOf(\App\Entity\ToastingSession::class, $session);

        $sourceToast = (new Toast())
            ->setWorkspace($workspace)
            ->setAuthor($workspace->getOrganizer())
            ->setTitle('Validate roadmap')
            ->setDescription('Need a go/no-go for the next quarter.')
            ->setStatus(Toast::STATUS_TOASTED)
            ->setDiscussionNotes('Decision: focus on two lighthouse accounts.')
            ->setOwner($workspace->getOrganizer())
            ->setDueAt(new \DateTimeImmutable('2026-04-08'));

        $followUpToast = (new Toast())
            ->setWorkspace($workspace)
            ->setAuthor($workspace->getOrganizer())
            ->setTitle('Send execution plan')
            ->setOwner($workspace->getOrganizer())
            ->setDueAt(new \DateTimeImmutable('2026-04-03'))
            ->setPreviousItem($sourceToast);

        $sourceToastComment = (new ToastComment())
            ->setToast($sourceToast)
            ->setAuthor($workspace->getOrganizer())
            ->setContent('We need a named owner before closing.');

        $sourceToast->addComment($sourceToastComment);

        $this->setDateTimeProperty($sourceToast, 'createdAt', new \DateTimeImmutable('2026-04-01 09:10:00'));
        $sourceToast->setStatusChangedAt(new \DateTimeImmutable('2026-04-01 09:40:00'));
        $this->setDateTimeProperty($followUpToast, 'createdAt', new \DateTimeImmutable('2026-04-01 09:45:00'));
        $this->setDateTimeProperty($sourceToastComment, 'createdAt', new \DateTimeImmutable('2026-04-01 09:30:00'));

        $workspace->getItems()->add($sourceToast);
        $workspace->getItems()->add($followUpToast);
        $sourceToast->getFollowUpChildren()->add($followUpToast);

        $builder = new ToastingSessionSummaryBuilder(new WorkspaceWorkflowService());
        $payload = $builder->buildPrompt($workspace, $session);

        self::assertSame(2, $payload['sourceItemCount']);
        self::assertStringContainsString('Validate roadmap', $payload['prompt']);
        self::assertStringContainsString('Decision: focus on two lighthouse accounts.', $payload['prompt']);
        self::assertStringContainsString('Send execution plan', $payload['prompt']);
        self::assertStringContainsString('We need a named owner before closing.', $payload['prompt']);
        self::assertStringContainsString('## Next steps by member', $payload['prompt']);
    }

    private function createUser(string $email, string $displayName): User
    {
        [$firstName, $lastName] = array_pad(explode(' ', $displayName, 2), 2, null);

        return (new User())
            ->setEmail($email)
            ->setFirstName($firstName)
            ->setLastName($lastName);
    }

    private function setDateTimeProperty(object $target, string $propertyName, \DateTimeImmutable $value): void
    {
        $property = new ReflectionProperty($target, $propertyName);
        $property->setValue($target, $value);
    }
}
