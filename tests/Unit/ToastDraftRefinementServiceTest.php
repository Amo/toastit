<?php

namespace App\Tests\Unit;

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
        ), new WorkspaceWorkflowService());

        $draft = $service->refine($workspace, 'Release', 'Need something clearer');

        self::assertSame('Clarify release scope', $draft['title']);
        self::assertStringContainsString('## Context', $draft['description']);
        self::assertStringContainsString('## Call to action', $draft['description']);
        self::assertSame($assignee->getId(), $draft['ownerId']);
        self::assertSame('2026-04-18', $draft['dueOn']);
    }
}
