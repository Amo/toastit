<?php

namespace App\Tests\Integration;

use App\Entity\Toast;
use App\Entity\User;
use App\Entity\Workspace;
use App\Entity\WorkspaceMember;
use App\Meeting\XaiTextService;
use App\Security\JwtTokenService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class WorkspaceFlowTest extends WebTestCase
{
    use MailpitTestTrait;

    public function testAuthenticatedUserCanCreateWorkspaceAddItemsAndVote(): void
    {
        $client = static::createClient();
        $email = sprintf('workspace-%s@example.com', time());
        $this->loginWithMagicLink($client, $email);
        $client->setServerParameter('HTTP_AUTHORIZATION', 'Bearer '.$this->createAccessTokenForEmail($email));

        $client->jsonRequest('POST', '/api/workspaces', ['name' => 'Produit']);
        self::assertResponseIsSuccessful();
        $workspaceId = $this->decodeJsonResponse($client)['workspaceId'];

        $title = sprintf('Sujet prioritaire %s', microtime(true));

        $client->jsonRequest('POST', sprintf('/api/workspaces/%d/items', $workspaceId), [
            'title' => $title,
            'description' => 'Discussion rapide',
        ]);
        self::assertResponseIsSuccessful();

        $itemId = $this->findToastIdByTitle($title);

        $client->jsonRequest('POST', sprintf('/api/items/%d/vote', $itemId));
        self::assertResponseIsSuccessful();

        $client->request('GET', sprintf('/api/workspaces/%d', $workspaceId));
        self::assertResponseIsSuccessful();
        $payload = json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $client->setServerParameter('HTTP_AUTHORIZATION', '');

        self::assertSame('Produit', $payload['workspace']['name']);
        self::assertSame(1, $payload['agendaItems'][0]['voteCount']);
    }

    public function testOrganizerCanInviteMembersAndToggleMeetingMode(): void
    {
        $client = static::createClient();
        $ownerEmail = sprintf('owner-%s@example.com', time());
        $memberEmail = sprintf('member-%s@example.com', time());
        $this->loginWithMagicLink($client, $ownerEmail);
        $client->setServerParameter('HTTP_AUTHORIZATION', 'Bearer '.$this->createAccessTokenForEmail($ownerEmail));

        $client->jsonRequest('POST', '/api/workspaces', ['name' => 'Delivery']);
        self::assertResponseIsSuccessful();
        $workspaceId = $this->decodeJsonResponse($client)['workspaceId'];

        $client->jsonRequest('POST', sprintf('/api/workspaces/%d/invite', $workspaceId), ['email' => $memberEmail]);
        self::assertResponseIsSuccessful();

        $client->jsonRequest('POST', sprintf('/api/workspaces/%d/meeting/start', $workspaceId));
        self::assertResponseIsSuccessful();

        $client->request('GET', sprintf('/api/workspaces/%d', $workspaceId));
        self::assertResponseIsSuccessful();
        $payload = json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $client->setServerParameter('HTTP_AUTHORIZATION', '');

        self::assertSame('live', $payload['workspace']['meetingMode']);
        self::assertContains($memberEmail, array_map(static fn (array $membership): string => $membership['user']['email'], $payload['memberships']));
    }

    public function testDiscussionCreatesFollowUpsInSameWorkspaceLinkedToOriginalItem(): void
    {
        $client = static::createClient();
        $ownerEmail = sprintf('discussion-owner-%s@example.com', time());
        $memberEmail = sprintf('discussion-member-%s@example.com', time());
        $this->loginWithMagicLink($client, $ownerEmail);
        $client->setServerParameter('HTTP_AUTHORIZATION', 'Bearer '.$this->createAccessTokenForEmail($ownerEmail));

        $client->jsonRequest('POST', '/api/workspaces', ['name' => 'Ops']);
        self::assertResponseIsSuccessful();
        $workspaceId = $this->decodeJsonResponse($client)['workspaceId'];
        $sourceTitle = sprintf('Sujet source %s', microtime(true));
        $client->jsonRequest('POST', sprintf('/api/workspaces/%d/invite', $workspaceId), ['email' => $memberEmail]);
        self::assertResponseIsSuccessful();
        $client->jsonRequest('POST', sprintf('/api/workspaces/%d/items', $workspaceId), [
            'title' => $sourceTitle,
            'description' => 'Point de depart',
        ]);
        self::assertResponseIsSuccessful();
        $client->jsonRequest('POST', sprintf('/api/workspaces/%d/meeting/start', $workspaceId));
        self::assertResponseIsSuccessful();

        $memberUserId = $this->findUserIdByEmail($memberEmail);
        $sourceToastId = $this->findToastIdByTitle($sourceTitle);

        $followUpTitle = sprintf('Envoyer le recap %s', microtime(true));
        $client->jsonRequest('POST', sprintf('/api/items/%d/discussion', $sourceToastId), [
            'discussionNotes' => 'Discussion finalisee.',
            'ownerId' => $memberUserId,
            'dueOn' => '2026-04-15',
            'followUpItems' => [[
                'title' => $followUpTitle,
                'ownerId' => $memberUserId,
                'dueOn' => '2026-04-15',
            ]],
        ]);
        self::assertResponseIsSuccessful();

        $client->request('GET', sprintf('/api/workspaces/%d', $workspaceId));
        self::assertResponseIsSuccessful();
        $payload = json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $client->setServerParameter('HTTP_AUTHORIZATION', '');

        self::assertSame('toasted', $payload['resolvedItems'][0]['status']);
        self::assertSame($followUpTitle, $payload['resolvedItems'][0]['followUpItems'][0]['title']);

        $followUp = static::getContainer()->get(EntityManagerInterface::class)
            ->getRepository(Toast::class)
            ->findOneBy(['title' => $followUpTitle]);

        self::assertInstanceOf(Toast::class, $followUp);
        self::assertSame($sourceTitle, $followUp->getPreviousItem()?->getTitle());
        self::assertSame($workspaceId, $followUp->getWorkspace()->getId());
    }

    public function testInvitedMemberCannotBoostOutsideOrganizerPermissions(): void
    {
        $ownerClient = static::createClient();
        $ownerEmail = sprintf('host-%s@example.com', time());
        $guestEmail = sprintf('guest-%s@example.com', time());
        $this->loginWithMagicLink($ownerClient, $ownerEmail);
        $ownerClient->setServerParameter('HTTP_AUTHORIZATION', 'Bearer '.$this->createAccessTokenForEmail($ownerEmail));

        $ownerClient->jsonRequest('POST', '/api/workspaces', ['name' => 'Review']);
        self::assertResponseIsSuccessful();
        $workspaceId = $this->decodeJsonResponse($ownerClient)['workspaceId'];
        $title = sprintf('Sujet prive %s', microtime(true));
        $ownerClient->jsonRequest('POST', sprintf('/api/workspaces/%d/invite', $workspaceId), ['email' => $guestEmail]);
        self::assertResponseIsSuccessful();
        $ownerClient->jsonRequest('POST', sprintf('/api/workspaces/%d/items', $workspaceId), [
            'title' => $title,
            'description' => 'A discuter',
        ]);
        self::assertResponseIsSuccessful();
        $ownerClient->jsonRequest('POST', sprintf('/api/workspaces/%d/meeting/start', $workspaceId));
        self::assertResponseIsSuccessful();
        $itemId = $this->findToastIdByTitle($title);

        static::ensureKernelShutdown();
        $guestClient = static::createClient();
        $this->loginWithMagicLink($guestClient, $guestEmail);
        $guestClient->setServerParameter('HTTP_AUTHORIZATION', 'Bearer '.$this->createAccessTokenForEmail($guestEmail));

        $guestClient->jsonRequest('POST', sprintf('/api/items/%d/boost', $itemId));
        self::assertResponseStatusCodeSame(403);

        $guestClient->jsonRequest('POST', sprintf('/api/items/%d/veto', $itemId));
        self::assertResponseStatusCodeSame(403);
    }

    public function testWorkspaceMemberCanGenerateLatestMeetingSummary(): void
    {
        $client = static::createClient();
        $ownerEmail = sprintf('summary-owner-%s@example.com', time());
        $memberEmail = sprintf('summary-member-%s@example.com', time());
        $this->loginWithMagicLink($client, $ownerEmail);
        $client->setServerParameter('HTTP_AUTHORIZATION', 'Bearer '.$this->createAccessTokenForEmail($ownerEmail));

        $client->jsonRequest('POST', '/api/workspaces', ['name' => 'Summary board']);
        self::assertResponseIsSuccessful();
        $workspaceId = $this->decodeJsonResponse($client)['workspaceId'];

        $client->jsonRequest('POST', sprintf('/api/workspaces/%d/invite', $workspaceId), ['email' => $memberEmail]);
        self::assertResponseIsSuccessful();

        $sourceTitle = sprintf('Decision point %s', microtime(true));
        $followUpTitle = sprintf('Follow-up %s', microtime(true));

        $client->jsonRequest('POST', sprintf('/api/workspaces/%d/items', $workspaceId), [
            'title' => $sourceTitle,
            'description' => 'Discuss the release scope.',
        ]);
        self::assertResponseIsSuccessful();

        $client->jsonRequest('POST', sprintf('/api/workspaces/%d/meeting/start', $workspaceId));
        self::assertResponseIsSuccessful();

        $sourceToastId = $this->findToastIdByTitle($sourceTitle);
        $memberUserId = $this->findUserIdByEmail($memberEmail);

        $client->jsonRequest('POST', sprintf('/api/items/%d/discussion', $sourceToastId), [
            'discussionNotes' => 'Ship with a limited beta and weekly checkpoint.',
            'ownerId' => $memberUserId,
            'dueOn' => '2026-04-15',
            'followUpItems' => [[
                'title' => $followUpTitle,
                'ownerId' => $memberUserId,
                'dueOn' => '2026-04-15',
            ]],
        ]);
        self::assertResponseIsSuccessful();

        $client->jsonRequest('POST', sprintf('/api/workspaces/%d/meeting/stop', $workspaceId));
        self::assertResponseIsSuccessful();

        static::ensureKernelShutdown();
        $client = static::createClient();
        $client->setServerParameter('HTTP_AUTHORIZATION', 'Bearer '.$this->createAccessTokenForEmail($ownerEmail));
        $client->disableReboot();
        static::getContainer()->set(XaiTextService::class, new XaiTextService(
            new MockHttpClient([
                new MockResponse(json_encode([
                    'output' => [[
                        'content' => [[
                            'type' => 'output_text',
                            'text' => "## Decisions\n- Limited beta approved\n\n## Follow-ups\n- {$followUpTitle}\n\n## Responsibilities by member\n- {$memberEmail}\n\n## Suggestions\n- Reduce scope earlier",
                        ]],
                    ]],
                ], JSON_THROW_ON_ERROR)),
            ]),
            'test-key',
            'https://api.x.ai/v1',
            'grok-4.20-reasoning',
            30,
        ));

        $client->jsonRequest('POST', sprintf('/api/workspaces/%d/meeting/summary', $workspaceId), []);
        self::assertResponseIsSuccessful();

        $payload = $this->decodeJsonResponse($client);

        self::assertTrue($payload['ok']);
        self::assertGreaterThan(0, $payload['summary']['id']);
        self::assertStringContainsString('Limited beta approved', $payload['summary']['summary']);
        self::assertStringContainsString($followUpTitle, $payload['summary']['summary']);
    }

    public function testStoppingMeetingPersistsSummaryAndOwnerCanEditIt(): void
    {
        $client = static::createClient();
        $ownerEmail = sprintf('archive-owner-%s@example.com', time());
        $this->loginWithMagicLink($client, $ownerEmail);
        $client->setServerParameter('HTTP_AUTHORIZATION', 'Bearer '.$this->createAccessTokenForEmail($ownerEmail));

        $client->jsonRequest('POST', '/api/workspaces', ['name' => 'Archive board']);
        self::assertResponseIsSuccessful();
        $workspaceId = $this->decodeJsonResponse($client)['workspaceId'];

        $client->jsonRequest('POST', sprintf('/api/workspaces/%d/items', $workspaceId), [
            'title' => sprintf('Archive topic %s', microtime(true)),
            'description' => 'Capture the closing recap.',
        ]);
        self::assertResponseIsSuccessful();

        $client->jsonRequest('POST', sprintf('/api/workspaces/%d/meeting/start', $workspaceId));
        self::assertResponseIsSuccessful();

        static::ensureKernelShutdown();
        $client = static::createClient();
        $client->setServerParameter('HTTP_AUTHORIZATION', 'Bearer '.$this->createAccessTokenForEmail($ownerEmail));
        $client->disableReboot();
        static::getContainer()->set(XaiTextService::class, new XaiTextService(
            new MockHttpClient([
                new MockResponse(json_encode([
                    'output' => [[
                        'content' => [[
                            'type' => 'output_text',
                            'text' => "## Decisions\n- Wrap the session cleanly",
                        ]],
                    ]],
                ], JSON_THROW_ON_ERROR)),
            ]),
            'test-key',
            'https://api.x.ai/v1',
            'grok-4.20-reasoning',
            30,
        ));

        $client->jsonRequest('POST', sprintf('/api/workspaces/%d/meeting/stop', $workspaceId));
        self::assertResponseIsSuccessful();
        $stopPayload = $this->decodeJsonResponse($client);

        self::assertTrue($stopPayload['ok']);
        self::assertGreaterThan(0, $stopPayload['sessionId']);
        self::assertStringContainsString('Wrap the session cleanly', $stopPayload['summary']['summary']);

        $client->request('GET', sprintf('/api/workspaces/%d', $workspaceId));
        self::assertResponseIsSuccessful();
        $workspacePayload = $this->decodeJsonResponse($client);

        self::assertStringContainsString('Wrap the session cleanly', $workspacePayload['toastingSessions'][0]['summary']);

        $client->request('PUT', sprintf('/api/workspaces/%d/sessions/%d/summary', $workspaceId, $stopPayload['sessionId']), server: ['CONTENT_TYPE' => 'application/json'], content: json_encode([
            'summary' => "## Decisions\n- Edited by owner",
        ], JSON_THROW_ON_ERROR));
        self::assertResponseIsSuccessful();

        $editedPayload = $this->decodeJsonResponse($client);

        self::assertTrue($editedPayload['ok']);
        self::assertStringContainsString('Edited by owner', $editedPayload['summary']['summary']);

        $client->request('GET', sprintf('/api/workspaces/%d', $workspaceId));
        self::assertResponseIsSuccessful();
        $workspacePayload = $this->decodeJsonResponse($client);

        self::assertStringContainsString('Edited by owner', $workspacePayload['toastingSessions'][0]['summary']);
    }

    public function testOwnerCanSendPersistedSessionSummaryByEmail(): void
    {
        $client = static::createClient();
        $ownerEmail = sprintf('summary-mail-owner-%s@example.com', time());
        $memberEmail = sprintf('summary-mail-member-%s@example.com', time());

        $this->loginWithMagicLink($client, $ownerEmail);
        $client->setServerParameter('HTTP_AUTHORIZATION', 'Bearer '.$this->createAccessTokenForEmail($ownerEmail));

        $client->jsonRequest('POST', '/api/workspaces', ['name' => 'Mail board']);
        self::assertResponseIsSuccessful();
        $workspaceId = $this->decodeJsonResponse($client)['workspaceId'];

        $client->jsonRequest('POST', sprintf('/api/workspaces/%d/invite', $workspaceId), ['email' => $memberEmail]);
        self::assertResponseIsSuccessful();

        $client->jsonRequest('POST', sprintf('/api/workspaces/%d/items', $workspaceId), [
            'title' => sprintf('Mail topic %s', microtime(true)),
            'description' => 'Include the recap by email.',
        ]);
        self::assertResponseIsSuccessful();

        $client->jsonRequest('POST', sprintf('/api/workspaces/%d/meeting/start', $workspaceId));
        self::assertResponseIsSuccessful();

        static::ensureKernelShutdown();
        $client = static::createClient();
        $client->setServerParameter('HTTP_AUTHORIZATION', 'Bearer '.$this->createAccessTokenForEmail($ownerEmail));
        $client->disableReboot();
        static::getContainer()->set(XaiTextService::class, new XaiTextService(
            new MockHttpClient([
                new MockResponse(json_encode([
                    'output' => [[
                        'content' => [[
                            'type' => 'output_text',
                            'text' => "## Decisions\n- Share recap for #123",
                        ]],
                    ]],
                ], JSON_THROW_ON_ERROR)),
            ]),
            'test-key',
            'https://api.x.ai/v1',
            'grok-4.20-reasoning',
            30,
        ));

        $client->jsonRequest('POST', sprintf('/api/workspaces/%d/meeting/stop', $workspaceId));
        self::assertResponseIsSuccessful();
        $sessionId = $this->decodeJsonResponse($client)['sessionId'];

        $this->clearMailpit();
        $client->jsonRequest('POST', sprintf('/api/workspaces/%d/sessions/%d/summary/send', $workspaceId, $sessionId));
        self::assertResponseIsSuccessful();

        $payload = $this->decodeJsonResponse($client);
        self::assertTrue($payload['ok']);
        self::assertSame(2, $payload['recipientCount']);

        $messages = $this->fetchMailpitMessages();
        self::assertCount(2, $messages);

        $message = $this->fetchMailpitMessage($messages[0]['ID']);
        self::assertStringContainsString('Toastit recap for Mail board session', $message['Subject']);
        self::assertStringContainsString('Share recap for #123', $message['Text']);
    }

    public function testWorkspaceMemberCanRefineToastDraftWithXai(): void
    {
        $client = static::createClient();
        $ownerEmail = sprintf('draft-owner-%s@example.com', time());
        $this->loginWithMagicLink($client, $ownerEmail);
        $client->setServerParameter('HTTP_AUTHORIZATION', 'Bearer '.$this->createAccessTokenForEmail($ownerEmail));

        $client->jsonRequest('POST', '/api/workspaces', ['name' => 'Draft board']);
        self::assertResponseIsSuccessful();
        $workspaceId = $this->decodeJsonResponse($client)['workspaceId'];

        static::ensureKernelShutdown();
        $client = static::createClient();
        $client->setServerParameter('HTTP_AUTHORIZATION', 'Bearer '.$this->createAccessTokenForEmail($ownerEmail));
        $memberUserId = $this->findUserIdByEmail($ownerEmail);
        $client->disableReboot();
        static::getContainer()->set(XaiTextService::class, new XaiTextService(
            new MockHttpClient([
                new MockResponse(json_encode([
                    'output' => [[
                        'content' => [[
                            'type' => 'output_text',
                            'text' => "TITLE: Decide launch scope\nASSIGNEE: {$ownerEmail}\nDUE_ON: 2026-04-22\nDESCRIPTION:\n## Situation\n- Keep the scope narrow.\n\n## Call to action\n- Approve or reject the scope in this session.",
                        ]],
                    ]],
                ], JSON_THROW_ON_ERROR)),
            ]),
            'test-key',
            'https://api.x.ai/v1',
            'grok-4.20-reasoning',
            30,
        ));

        $client->jsonRequest('POST', sprintf('/api/workspaces/%d/items/draft/refine', $workspaceId), [
            'title' => 'launch',
            'description' => 'maybe discuss broad scope',
        ]);
        self::assertResponseIsSuccessful();

        $payload = $this->decodeJsonResponse($client);

        self::assertTrue($payload['ok']);
        self::assertSame('Decide launch scope', $payload['draft']['title']);
        self::assertStringContainsString('## Call to action', $payload['draft']['description']);
        self::assertSame($memberUserId, $payload['draft']['ownerId']);
        self::assertSame('2026-04-22', $payload['draft']['dueOn']);
    }

    public function testOwnerCanGenerateAndApplyToastCurationDraft(): void
    {
        $client = static::createClient();
        $ownerEmail = sprintf('curation-owner-%s@example.com', time());
        $this->loginWithMagicLink($client, $ownerEmail);
        $client->setServerParameter('HTTP_AUTHORIZATION', 'Bearer '.$this->createAccessTokenForEmail($ownerEmail));

        $client->jsonRequest('POST', '/api/workspaces', ['name' => 'Curation board']);
        self::assertResponseIsSuccessful();
        $workspaceId = $this->decodeJsonResponse($client)['workspaceId'];

        $title = sprintf('Legacy wording %s', microtime(true));
        $client->jsonRequest('POST', sprintf('/api/workspaces/%d/items', $workspaceId), [
            'title' => $title,
            'description' => 'This should be tightened up before the meeting.',
        ]);
        self::assertResponseIsSuccessful();

        $toastId = $this->findToastIdByTitle($title);

        static::ensureKernelShutdown();
        $client = static::createClient();
        $client->setServerParameter('HTTP_AUTHORIZATION', 'Bearer '.$this->createAccessTokenForEmail($ownerEmail));
        $client->disableReboot();
        static::getContainer()->set(XaiTextService::class, new XaiTextService(
            new MockHttpClient([
                new MockResponse(json_encode([
                    'output' => [[
                        'content' => [[
                            'type' => 'output_text',
                            'text' => json_encode([
                                'summary' => 'Tighten the main active toast before the next review.',
                                'actions' => [
                                    [
                                        'type' => 'update_toast',
                                        'toastId' => $toastId,
                                        'reason' => 'Make the toast decision-ready.',
                                        'title' => 'Clarify decision scope',
                                        'description' => "## Context\n- We need a sharper framing.\n\n## Call to action\n- Decide whether to approve the narrowed scope.",
                                    ],
                                    [
                                        'type' => 'add_comment',
                                        'toastId' => $toastId,
                                        'reason' => 'Capture the curation rationale directly on the toast.',
                                        'content' => 'Prepared for owner review before the next workspace session.',
                                    ],
                                ],
                            ], JSON_THROW_ON_ERROR),
                        ]],
                    ]],
                ], JSON_THROW_ON_ERROR)),
            ]),
            'test-key',
            'https://api.x.ai/v1',
            'grok-4.20-reasoning',
            30,
        ));

        $client->jsonRequest('POST', sprintf('/api/workspaces/%d/curation/draft', $workspaceId));
        self::assertResponseIsSuccessful();
        $draftPayload = $this->decodeJsonResponse($client);

        self::assertTrue($draftPayload['ok']);
        self::assertCount(2, $draftPayload['draft']['actions']);
        self::assertSame('update_toast', $draftPayload['draft']['actions'][0]['type']);

        $client->jsonRequest('POST', sprintf('/api/workspaces/%d/curation/apply', $workspaceId), [
            'actions' => $draftPayload['draft']['actions'],
        ]);
        self::assertResponseIsSuccessful();
        $applyPayload = $this->decodeJsonResponse($client);

        self::assertTrue($applyPayload['ok']);
        self::assertCount(2, $applyPayload['result']['applied']);
        self::assertCount(0, $applyPayload['result']['skipped']);

        $client->request('GET', sprintf('/api/workspaces/%d', $workspaceId));
        self::assertResponseIsSuccessful();
        $workspacePayload = $this->decodeJsonResponse($client);

        self::assertSame('Clarify decision scope', $workspacePayload['agendaItems'][0]['title']);
        self::assertStringContainsString('Prepared for owner review', $workspacePayload['agendaItems'][0]['comments'][0]['content']);
    }

    public function testOwnerCanSaveDecisionNotesGenerateExecutionPlanAndApplyFollowUpsOneByOne(): void
    {
        $client = static::createClient();
        $ownerEmail = sprintf('execution-owner-%s@example.com', time());
        $memberEmail = sprintf('execution-member-%s@example.com', time());
        $this->loginWithMagicLink($client, $ownerEmail);
        $client->setServerParameter('HTTP_AUTHORIZATION', 'Bearer '.$this->createAccessTokenForEmail($ownerEmail));

        $client->jsonRequest('POST', '/api/workspaces', ['name' => 'Execution board']);
        self::assertResponseIsSuccessful();
        $workspaceId = $this->decodeJsonResponse($client)['workspaceId'];

        $client->jsonRequest('POST', sprintf('/api/workspaces/%d/invite', $workspaceId), ['email' => $memberEmail]);
        self::assertResponseIsSuccessful();

        $title = sprintf('Decision source %s', microtime(true));
        $client->jsonRequest('POST', sprintf('/api/workspaces/%d/items', $workspaceId), [
            'title' => $title,
            'description' => 'We need a structured execution plan.',
        ]);
        self::assertResponseIsSuccessful();

        $toastId = $this->findToastIdByTitle($title);
        $memberUserId = $this->findUserIdByEmail($memberEmail);

        $client->jsonRequest('POST', sprintf('/api/workspaces/%d/meeting/start', $workspaceId));
        self::assertResponseIsSuccessful();

        $client->jsonRequest('POST', sprintf('/api/items/%d/decision-notes', $toastId), [
            'discussionNotes' => 'Decision made: prepare the internal rollout and customer communication.',
            'ownerId' => $memberUserId,
            'dueOn' => '2026-04-30',
        ]);
        self::assertResponseIsSuccessful();

        static::ensureKernelShutdown();
        $client = static::createClient();
        $client->setServerParameter('HTTP_AUTHORIZATION', 'Bearer '.$this->createAccessTokenForEmail($ownerEmail));
        $client->disableReboot();
        static::getContainer()->set(XaiTextService::class, new XaiTextService(
            new MockHttpClient([
                new MockResponse(json_encode([
                    'output' => [[
                        'content' => [[
                            'type' => 'output_text',
                            'text' => json_encode([
                                'summary' => 'Create a tight rollout plan from the decision notes.',
                                'actions' => [
                                    [
                                        'type' => 'create_follow_up',
                                        'toastId' => $toastId,
                                        'reason' => 'The team needs an owner and a concrete rollout artifact.',
                                        'title' => 'Draft rollout checklist',
                                        'description' => "## Outcome\n- Draft the internal rollout checklist.\n\n## Call to action\n- Share the checklist for review.",
                                        'ownerId' => $memberUserId,
                                        'dueOn' => '2026-04-18',
                                    ],
                                ],
                            ], JSON_THROW_ON_ERROR),
                        ]],
                    ]],
                ], JSON_THROW_ON_ERROR)),
            ]),
            'test-key',
            'https://api.x.ai/v1',
            'grok-4.20-reasoning',
            30,
        ));

        $client->jsonRequest('POST', sprintf('/api/items/%d/execution-plan/draft', $toastId));
        self::assertResponseIsSuccessful();
        $draftPayload = $this->decodeJsonResponse($client);

        self::assertTrue($draftPayload['ok']);
        self::assertCount(1, $draftPayload['draft']['actions']);
        self::assertSame('Draft rollout checklist', $draftPayload['draft']['actions'][0]['title']);

        $client->jsonRequest('POST', sprintf('/api/items/%d/execution-plan/apply', $toastId), [
            'action' => $draftPayload['draft']['actions'][0],
        ]);
        self::assertResponseIsSuccessful();
        $applyPayload = $this->decodeJsonResponse($client);

        self::assertTrue($applyPayload['ok']);
        self::assertCount(1, $applyPayload['result']['applied']);

        $client->request('GET', sprintf('/api/workspaces/%d', $workspaceId));
        self::assertResponseIsSuccessful();
        $workspacePayload = $this->decodeJsonResponse($client);
        $sourceToastPayload = array_values(array_filter(
            $workspacePayload['agendaItems'],
            static fn (array $item): bool => $item['id'] === $toastId,
        ))[0] ?? null;

        self::assertNotNull($sourceToastPayload);
        self::assertSame('Decision made: prepare the internal rollout and customer communication.', $sourceToastPayload['discussionNotes']);
        self::assertSame('Draft rollout checklist', $sourceToastPayload['followUpItems'][0]['title']);
    }

    private function loginWithMagicLink(KernelBrowser $client, string $email): void
    {
        $this->clearMailpit();

        $client->jsonRequest('POST', '/api/auth/request-otp', ['email' => $email]);
        self::assertResponseIsSuccessful();
        $payload = $this->fetchSingleMailpitMessage();
        preg_match('/\R([0-9]{3}) ([0-9]{3})\R\RCe code expire/', $payload['Text'], $match);
        $client->jsonRequest('POST', '/api/auth/verify-otp', [
            'email' => $email,
            'purpose' => 'login',
            'code' => $match[1].$match[2],
        ]);
        self::assertResponseIsSuccessful();
        $verifyPayload = json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $client->jsonRequest('POST', '/api/auth/pin/setup', [
            'pinSetupToken' => $verifyPayload['pinSetupToken'],
            'pin' => '1234',
            'pinConfirmation' => '1234',
        ]);
        self::assertResponseIsSuccessful();
    }

    private function findUserIdByEmail(string $email): int
    {
        $user = static::getContainer()->get(EntityManagerInterface::class)
            ->getRepository(User::class)
            ->findOneBy(['email' => $email]);

        self::assertInstanceOf(User::class, $user);

        return $user->getId();
    }

    private function findToastIdByTitle(string $title): int
    {
        $toast = static::getContainer()->get(EntityManagerInterface::class)
            ->getRepository(Toast::class)
            ->findOneBy(['title' => $title]);

        self::assertInstanceOf(Toast::class, $toast);

        return $toast->getId();
    }

    private function decodeJsonResponse(KernelBrowser $client): array
    {
        return json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
    }

    private function createAccessTokenForEmail(string $email): string
    {
        $user = static::getContainer()->get(EntityManagerInterface::class)
            ->getRepository(User::class)
            ->findOneBy(['email' => $email]);

        self::assertInstanceOf(User::class, $user);

        return static::getContainer()->get(JwtTokenService::class)
            ->createAccessToken($user, new \DateTimeImmutable());
    }
}
