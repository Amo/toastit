<?php

namespace App\Tests\Unit;

use App\Ai\AiPromptTemplateService;
use App\Meeting\XaiTextService;
use App\Entity\User;
use App\Entity\Workspace;
use App\Workspace\ToastDraftRefinementService;
use App\Workspace\WorkspaceWorkflowService;
use App\Tests\Support\ReflectionHelper;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class ToastDraftRefinementServiceTest extends TestCase
{
    public function testRefineParsesTitleAndDescriptionFromXaiResponse(): void
    {
        $workspace = (new Workspace())
            ->setName('Draft board')
            ->setOrganizer((new User())->setEmail('owner@example.com')->setFirstName('Owner'));
        ReflectionHelper::setId($workspace->getOrganizer(), 1);

        $assignee = (new User())
            ->setEmail('sofiane@example.com')
            ->setFirstName('Sofiane');
        ReflectionHelper::setId($assignee, 2);

        $membership = new \App\Entity\WorkspaceMember();
        $membership->setWorkspace($workspace)->setUser($assignee);
        $workspace->addMembership($membership);

        $promptTemplate = $this->createMock(AiPromptTemplateService::class);
        $promptTemplate
            ->method('resolveSystemPrompt')
            ->willReturn('system prompt');

        $service = new ToastDraftRefinementService(new XaiTextService(
            new MockHttpClient([
                new MockResponse(json_encode([
                    'output' => [[
                        'content' => [[
                            'type' => 'output_text',
                            'text' => "TITLE: Clarify release scope\nASSIGNEE: Sofiane\nDUE_ON: 2026-04-18\nDESCRIPTION:\n## Context\n- Focus on the narrow beta.\n\n## Call to action\n- Decide go/no-go today.",
                        ]],
                    ]],
                ], JSON_THROW_ON_ERROR)),
            ]),
            'test-key',
            'https://api.x.ai/v1',
            'grok-4.20-reasoning',
            30,
        ), new WorkspaceWorkflowService(), $promptTemplate);

        $draft = $service->refine($workspace, 'Release', 'Need something clearer');

        self::assertSame('Clarify release scope', $draft['title']);
        self::assertStringContainsString('## Context', $draft['description']);
        self::assertStringContainsString('## Call to action', $draft['description']);
        self::assertSame($assignee->getId(), $draft['ownerId']);
        self::assertSame('2026-04-18', $draft['dueOn']);
    }

    public function testRefineAcceptsIsoDatetimeAndNormalizesDueOnDate(): void
    {
        $workspace = (new Workspace())
            ->setName('Draft board')
            ->setOrganizer((new User())->setEmail('owner@example.com')->setFirstName('Owner'));
        ReflectionHelper::setId($workspace->getOrganizer(), 1);

        $promptTemplate = $this->createMock(AiPromptTemplateService::class);
        $promptTemplate
            ->method('resolveSystemPrompt')
            ->willReturn('system prompt');

        $service = new ToastDraftRefinementService(new XaiTextService(
            new MockHttpClient([
                new MockResponse(json_encode([
                    'output' => [[
                        'content' => [[
                            'type' => 'output_text',
                            'text' => "TITLE: Ship before weekend\nASSIGNEE: NONE\nDUE_ON: 2026-04-18T19:30:00+02:00\nDESCRIPTION:\nUse the explicit schedule from the email.",
                        ]],
                    ]],
                ], JSON_THROW_ON_ERROR)),
            ]),
            'test-key',
            'https://api.x.ai/v1',
            'grok-4.20-reasoning',
            30,
        ), new WorkspaceWorkflowService(), $promptTemplate);

        $draft = $service->refine($workspace, 'Ship before weekend', 'Need this done this Saturday at 19:30.');

        self::assertSame('Ship before weekend', $draft['title']);
        self::assertNull($draft['ownerId']);
        self::assertSame('2026-04-18', $draft['dueOn']);
    }
}
