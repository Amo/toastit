<?php

namespace App\Tests\Unit;

use App\Ai\AiPromptTemplateService;
use App\Entity\Toast;
use App\Entity\User;
use App\Entity\Workspace;
use App\Meeting\SessionSummaryUnavailableException;
use App\Meeting\XaiTextService;
use App\Tests\Support\ReflectionHelper;
use App\Workspace\ToastExecutionPlanDraftService;
use App\Workspace\WorkspaceWorkflowService;
use PHPUnit\Framework\TestCase;

final class ToastExecutionPlanDraftServiceTest extends TestCase
{
    public function testGenerateUsesDecisionNotesOverrideWithoutPersistedNotes(): void
    {
        $owner = (new User())->setEmail('owner@example.com')->setFirstName('Owner');
        ReflectionHelper::setId($owner, 5);

        $workspace = (new Workspace())
            ->setName('Board')
            ->setOrganizer($owner);

        $toast = (new Toast())
            ->setWorkspace($workspace)
            ->setAuthor($owner)
            ->setTitle('Clarify rollout')
            ->setDescription('Need next steps');
        ReflectionHelper::setId($toast, 42);

        $promptTemplate = $this->createMock(AiPromptTemplateService::class);
        $promptTemplate
            ->method('resolveSystemPrompt')
            ->willReturn('system');
        $promptTemplate
            ->expects(self::once())
            ->method('resolveUserPromptTemplate')
            ->with(
                'toast_execution_plan_system',
                '{{ context_text }}',
                self::callback(static function (array $variables): bool {
                    return ($variables['source_toast']['decision_notes'] ?? null) === 'Draft notes not saved yet';
                }),
            )
            ->willReturn('prompt');

        $xai = $this->createMock(XaiTextService::class);
        $xai
            ->expects(self::once())
            ->method('generateTextForUser')
            ->with(
                $owner,
                'system',
                'prompt',
                ['source' => 'toast_execution_plan'],
            )
            ->willReturn(json_encode([
                'result' => [
                    'summary' => 'Plan ready',
                    'actions' => [[
                        'type' => 'create_follow_up',
                        'title' => 'Draft follow-up',
                        'ownerId' => 5,
                        'dueOn' => '2026-04-24',
                    ]],
                ],
            ], JSON_THROW_ON_ERROR));

        $service = new ToastExecutionPlanDraftService($xai, new WorkspaceWorkflowService(), $promptTemplate);
        $draft = $service->generate($toast, $owner, 'Draft notes not saved yet');

        self::assertSame('Plan ready', $draft['summary']);
        self::assertSame('Draft follow-up', $draft['actions'][0]['title']);
        self::assertSame(42, $draft['actions'][0]['toastId']);
    }

    public function testGenerateRejectsMissingDecisionNotesWhenNothingProvided(): void
    {
        $owner = (new User())->setEmail('owner@example.com')->setFirstName('Owner');
        $workspace = (new Workspace())
            ->setName('Board')
            ->setOrganizer($owner);

        $toast = (new Toast())
            ->setWorkspace($workspace)
            ->setAuthor($owner)
            ->setTitle('Clarify rollout');

        $service = new ToastExecutionPlanDraftService(
            $this->createMock(XaiTextService::class),
            new WorkspaceWorkflowService(),
            $this->createMock(AiPromptTemplateService::class),
        );

        $this->expectException(SessionSummaryUnavailableException::class);
        $this->expectExceptionMessage('Decision notes are required before generating an execution plan.');
        $service->generate($toast, $owner);
    }
}
