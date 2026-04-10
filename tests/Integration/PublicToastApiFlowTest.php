<?php

namespace App\Tests\Integration;

use App\Entity\Toast;
use App\Entity\User;
use App\Security\JwtTokenService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class PublicToastApiFlowTest extends WebTestCase
{
    use MailpitTestTrait;

    private const PUBLIC_ACCEPT = 'application/vnd.toastit.public+json; version=1';

    public function testPublicApiCanCreateAndManipulateToastsUsingPersonalAccessToken(): void
    {
        $client = static::createClient();
        $ownerEmail = sprintf('public-owner-%s@example.com', time());
        $memberEmail = sprintf('public-member-%s@example.com', time());
        $this->loginWithMagicLink($client, $ownerEmail);
        $client->setServerParameter('HTTP_AUTHORIZATION', 'Bearer '.$this->createAccessTokenForEmail($ownerEmail));

        $client->jsonRequest('POST', '/api/workspaces', ['name' => 'Public API board']);
        self::assertResponseIsSuccessful();
        $workspaceId = $this->decodeJsonResponse($client)['workspaceId'];

        $client->jsonRequest('POST', sprintf('/api/workspaces/%d/invite', $workspaceId), ['email' => $memberEmail]);
        self::assertResponseIsSuccessful();

        $client->jsonRequest('POST', '/api/profile/personal-tokens', [
            'name' => 'Integration token',
        ]);
        self::assertResponseStatusCodeSame(201);
        $personalToken = $this->decodeJsonResponse($client)['token']['plainTextToken'];

        $client->setServerParameter('HTTP_AUTHORIZATION', 'Bearer '.$personalToken);
        $client->setServerParameter('HTTP_ACCEPT', self::PUBLIC_ACCEPT);
        $client->setServerParameter('HTTP_HOST', $this->publicApiHost());

        $publicWorkspaceName = sprintf('PAT workspace %s', microtime(true));
        $client->jsonRequest('POST', '/workspaces', ['name' => $publicWorkspaceName]);
        self::assertResponseStatusCodeSame(201);
        $publicWorkspaceId = (int) $this->decodeJsonResponse($client)['workspace']['id'];

        $client->request('GET', sprintf('/workspaces/%d/members', $publicWorkspaceId));
        self::assertResponseIsSuccessful();
        $publicMembers = $this->decodeJsonResponse($client)['members'] ?? [];
        self::assertCount(1, $publicMembers);
        self::assertTrue((bool) ($publicMembers[0]['isOwner'] ?? false));

        $client->jsonRequest('POST', sprintf('/workspaces/%d/members', $publicWorkspaceId), [
            'email' => $memberEmail,
        ]);
        self::assertResponseStatusCodeSame(201);
        $invitedMemberId = (int) $this->decodeJsonResponse($client)['member']['memberId'];

        $client->jsonRequest('PATCH', sprintf('/workspaces/%d/name', $publicWorkspaceId), [
            'name' => 'Public API board (renamed)',
        ]);
        self::assertResponseIsSuccessful();

        $client->jsonRequest('DELETE', sprintf('/workspaces/%d/members/%d', $publicWorkspaceId, $invitedMemberId));
        self::assertResponseIsSuccessful();

        $client->jsonRequest('POST', '/workspaces', ['name' => 'Public API board (to delete)']);
        self::assertResponseStatusCodeSame(201);
        $workspaceToDeleteId = (int) $this->decodeJsonResponse($client)['workspace']['id'];

        $client->jsonRequest('DELETE', sprintf('/workspaces/%d', $workspaceToDeleteId));
        self::assertResponseIsSuccessful();

        $client->request('GET', '/workspaces');
        self::assertResponseIsSuccessful();
        $workspaceList = $this->decodeJsonResponse($client)['workspaces'] ?? [];
        self::assertContains($workspaceId, array_map(static fn (array $workspace): int => (int) ($workspace['id'] ?? 0), $workspaceList));
        self::assertContains($publicWorkspaceId, array_map(static fn (array $workspace): int => (int) ($workspace['id'] ?? 0), $workspaceList));

        $title = sprintf('Public toast %s', microtime(true));
        $client->jsonRequest('POST', sprintf('/workspaces/%d/toasts', $workspaceId), [
            'title' => $title,
            'description' => 'Created through public API',
            'assigneeEmail' => $memberEmail,
            'dueOn' => '2026-04-25',
        ]);
        self::assertResponseStatusCodeSame(201);
        $toastId = (int) $this->decodeJsonResponse($client)['toast']['id'];

        $client->request('GET', sprintf('/toasts/%d', $toastId));
        self::assertResponseIsSuccessful();
        self::assertSame($toastId, (int) $this->decodeJsonResponse($client)['toast']['id']);

        $client->jsonRequest('PATCH', sprintf('/toasts/%d/title', $toastId), [
            'title' => sprintf('%s (edited)', $title),
        ]);
        self::assertResponseIsSuccessful();

        $client->jsonRequest('PATCH', sprintf('/toasts/%d/status', $toastId), [
            'status' => 'discarded',
        ]);
        self::assertResponseIsSuccessful();

        $client->jsonRequest('PATCH', sprintf('/toasts/%d/status', $toastId), [
            'status' => 'new',
        ]);
        self::assertResponseIsSuccessful();

        $client->request('GET', sprintf('/workspaces/%d/toasts?status=new&page=1&perPage=10', $workspaceId));
        self::assertResponseIsSuccessful();
        $toastsPayload = $this->decodeJsonResponse($client);
        self::assertSame(1, $toastsPayload['pagination']['page']);
        self::assertSame(10, $toastsPayload['pagination']['perPage']);
        self::assertContains($toastId, array_map(static fn (array $toast): int => (int) ($toast['id'] ?? 0), $toastsPayload['toasts'] ?? []));

        $client->jsonRequest('POST', sprintf('/workspaces/%d/toasts', $workspaceId), [
            'title' => 'Toast to transfer',
        ]);
        self::assertResponseStatusCodeSame(201);
        $toastToTransferId = (int) $this->decodeJsonResponse($client)['toast']['id'];

        $client->jsonRequest('PATCH', sprintf('/toasts/%d/workspace', $toastToTransferId), [
            'workspaceId' => $publicWorkspaceId,
        ]);
        self::assertResponseIsSuccessful();
        $transferredToastId = (int) $this->decodeJsonResponse($client)['toast']['id'];
        self::assertGreaterThan(0, $transferredToastId);

        $client->jsonRequest('PATCH', sprintf('/toasts/%d/assignee', $toastId), [
            'assigneeEmail' => '',
        ]);
        self::assertResponseIsSuccessful();

        $client->jsonRequest('PATCH', sprintf('/toasts/%d/due-date', $toastId), [
            'dueOn' => '2026-04-30',
        ]);
        self::assertResponseIsSuccessful();

        $markdownDescription = "## MCP update\n\n- endpoint: `PATCH /toasts/{id}/description`\n- format: markdown";
        $client->jsonRequest('PATCH', sprintf('/toasts/%d/description', $toastId), [
            'description' => $markdownDescription,
        ]);
        self::assertResponseIsSuccessful();
        self::assertSame($markdownDescription, $this->decodeJsonResponse($client)['toast']['description'] ?? null);

        $client->jsonRequest('POST', sprintf('/toasts/%d/comments', $toastId), [
            'content' => 'Comment from PAT flow',
        ]);
        self::assertResponseStatusCodeSame(201);

        $client->request('GET', sprintf('/toasts/%d/comments?page=1&perPage=5', $toastId));
        self::assertResponseIsSuccessful();
        $commentsPayload = $this->decodeJsonResponse($client);
        self::assertSame(1, $commentsPayload['pagination']['page']);
        self::assertSame(5, $commentsPayload['pagination']['perPage']);
        self::assertNotEmpty($commentsPayload['comments']);
        self::assertSame('Comment from PAT flow', $commentsPayload['comments'][0]['content'] ?? null);

        $client->jsonRequest('PUT', sprintf('/toasts/%d/vote', $toastId), [
            'voted' => true,
        ]);
        self::assertResponseIsSuccessful();

        $client->jsonRequest('PUT', sprintf('/toasts/%d/boost', $toastId), [
            'boosted' => true,
        ]);
        self::assertResponseIsSuccessful();

        $toast = static::getContainer()->get(EntityManagerInterface::class)
            ->getRepository(Toast::class)
            ->find($toastId);

        self::assertInstanceOf(Toast::class, $toast);
        self::assertSame('Public API board', $toast->getWorkspace()->getName());
        self::assertSame('2026-04-30', $toast->getDueAt()?->format('Y-m-d'));
        self::assertSame($markdownDescription, $toast->getDescription());
        self::assertNull($toast->getOwner());
        self::assertTrue($toast->isBoosted());
        self::assertSame(1, $toast->getVoteCount());
        self::assertSame('Comment from PAT flow', $toast->getComments()->first()->getContent());
    }

    public function testPublicApiRejectsMissingAcceptVersionAndRevokedToken(): void
    {
        $client = static::createClient();
        $ownerEmail = sprintf('public-auth-%s@example.com', time());
        $this->loginWithMagicLink($client, $ownerEmail);
        $client->setServerParameter('HTTP_AUTHORIZATION', 'Bearer '.$this->createAccessTokenForEmail($ownerEmail));

        $client->jsonRequest('POST', '/api/workspaces', ['name' => 'Public auth board']);
        self::assertResponseIsSuccessful();
        $workspaceId = $this->decodeJsonResponse($client)['workspaceId'];

        $client->jsonRequest('POST', '/api/profile/personal-tokens', [
            'name' => 'Revoked token',
        ]);
        self::assertResponseStatusCodeSame(201);
        $tokenPayload = $this->decodeJsonResponse($client)['token'];
        $tokenId = (int) $tokenPayload['id'];
        $personalToken = (string) $tokenPayload['plainTextToken'];

        $client->setServerParameter('HTTP_AUTHORIZATION', 'Bearer '.$personalToken);
        $client->setServerParameter('HTTP_ACCEPT', '');
        $client->setServerParameter('HTTP_HOST', $this->publicApiHost());
        $client->jsonRequest('POST', sprintf('/workspaces/%d/toasts', $workspaceId), [
            'title' => 'No accept',
        ]);
        self::assertResponseStatusCodeSame(406);

        $client->setServerParameter('HTTP_AUTHORIZATION', 'Bearer '.$this->createAccessTokenForEmail($ownerEmail));
        $client->setServerParameter('HTTP_HOST', 'localhost');
        $client->jsonRequest('DELETE', sprintf('/api/profile/personal-tokens/%d', $tokenId));
        self::assertResponseIsSuccessful();

        $client->setServerParameter('HTTP_AUTHORIZATION', 'Bearer '.$personalToken);
        $client->setServerParameter('HTTP_ACCEPT', self::PUBLIC_ACCEPT);
        $client->setServerParameter('HTTP_HOST', $this->publicApiHost());
        $client->jsonRequest('POST', sprintf('/workspaces/%d/toasts', $workspaceId), [
            'title' => 'Revoked token',
        ]);
        self::assertResponseStatusCodeSame(401);
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

    private function createAccessTokenForEmail(string $email): string
    {
        $user = static::getContainer()->get(EntityManagerInterface::class)
            ->getRepository(User::class)
            ->findOneBy(['email' => $email]);

        self::assertInstanceOf(User::class, $user);

        return static::getContainer()->get(JwtTokenService::class)
            ->createAccessToken($user, new \DateTimeImmutable());
    }

    private function decodeJsonResponse(KernelBrowser $client): array
    {
        return json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
    }

    private function publicApiHost(): string
    {
        return (string) ($_ENV['PUBLIC_API_HOST'] ?? $_SERVER['PUBLIC_API_HOST'] ?? 'api.toastit.test');
    }
}
