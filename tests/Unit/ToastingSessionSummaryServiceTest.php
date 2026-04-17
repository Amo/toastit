<?php

namespace App\Tests\Unit;

use App\Ai\AiPromptTemplateService;
use App\Entity\ToastingSession;
use App\Entity\User;
use App\Entity\Workspace;
use App\Meeting\ToastingSessionSummaryBuilder;
use App\Meeting\ToastingSessionSummaryService;
use App\Meeting\XaiTextService;
use App\Workspace\WorkspaceWorkflowService;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

final class ToastingSessionSummaryServiceTest extends TestCase
{
    public function testGenerateSessionSummaryExtractsMarkdownFromFencedJsonResponse(): void
    {
        $workspace = (new Workspace())
            ->setName('Executive committee')
            ->setOrganizer($this->createUser('owner@example.com', 'Owner'));

        $workspace->startMeetingMode($workspace->getOrganizer(), new \DateTimeImmutable('2026-04-01 09:00:00'));
        $workspace->stopMeetingMode($workspace->getOrganizer(), new \DateTimeImmutable('2026-04-01 10:00:00'));
        $session = $workspace->getToastingSessions()->first();

        self::assertInstanceOf(ToastingSession::class, $session);

        $promptTemplate = $this->createMock(AiPromptTemplateService::class);
        $promptTemplate
            ->method('resolveSystemPrompt')
            ->with('session_summary_system', '')
            ->willReturn('Return strict JSON');
        $promptTemplate
            ->method('resolveUserPromptTemplate')
            ->willReturn('summary context');

        $xai = $this->createMock(XaiTextService::class);
        $xai
            ->expects(self::once())
            ->method('generateSummaryForUser')
            ->willReturn(<<<'TEXT'
```json
{"result":{"markdown":"## Decisions\n- Ship the recap"}}
```
TEXT);

        $service = new ToastingSessionSummaryService(
            new ToastingSessionSummaryBuilder(new WorkspaceWorkflowService()),
            $xai,
            $promptTemplate,
        );

        $service->generateSessionSummary($workspace, $session, $workspace->getOrganizer());

        self::assertSame("## Decisions\n- Ship the recap", $session->getSummary());
    }

    private function createUser(string $email, string $displayName): User
    {
        [$firstName, $lastName] = array_pad(explode(' ', $displayName, 2), 2, null);

        return (new User())
            ->setEmail($email)
            ->setFirstName($firstName)
            ->setLastName($lastName);
    }
}
