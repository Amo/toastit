<?php

namespace App\Tests\Integration;

use App\Entity\User;
use App\Entity\WorkspaceNote;
use App\Security\JwtTokenService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class WorkspaceNoteFlowTest extends WebTestCase
{
    use MailpitTestTrait;

    public function testWorkspaceMemberCanCreateEditRevertAndReadNotesFromWorkspacePayload(): void
    {
        $ownerEmail = sprintf('notes-owner-%s@example.com', time());
        $memberEmail = sprintf('notes-member-%s@example.com', time());

        $ownerClient = static::createClient();
        $this->loginWithMagicLink($ownerClient, $ownerEmail);
        $ownerClient->setServerParameter('HTTP_AUTHORIZATION', 'Bearer '.$this->createAccessTokenForEmail($ownerEmail));

        $ownerClient->jsonRequest('POST', '/api/workspaces', ['name' => 'Notes workspace']);
        self::assertResponseIsSuccessful();
        $workspaceId = $this->decodeJsonResponse($ownerClient)['workspaceId'];

        $ownerClient->jsonRequest('POST', sprintf('/api/workspaces/%d/invite', $workspaceId), ['email' => $memberEmail]);
        self::assertResponseIsSuccessful();

        static::ensureKernelShutdown();
        $memberClient = static::createClient();
        $this->loginWithMagicLink($memberClient, $memberEmail);
        $memberClient->setServerParameter('HTTP_AUTHORIZATION', 'Bearer '.$this->createAccessTokenForEmail($memberEmail));

        $memberClient->jsonRequest('POST', sprintf('/api/workspaces/%d/notes', $workspaceId), [
            'title' => 'Workspace contract',
            'body' => "## Decisions\n- Keep the payload stable.",
            'isImportant' => true,
        ]);
        self::assertResponseIsSuccessful();
        $createdPayload = $this->decodeJsonResponse($memberClient);

        self::assertTrue($createdPayload['ok']);
        self::assertSame('Workspace contract', $createdPayload['note']['title']);
        self::assertCount(1, $createdPayload['note']['versions']);

        $noteId = (int) $createdPayload['note']['id'];
        $initialVersionId = (int) $createdPayload['note']['versions'][0]['id'];

        $memberClient->request('PUT', sprintf('/api/workspaces/%d/notes/%d', $workspaceId, $noteId), server: ['CONTENT_TYPE' => 'application/json'], content: json_encode([
            'title' => 'Workspace contract v2',
            'body' => "## Decisions\n- Keep the payload stable.\n- Track history.",
            'isImportant' => false,
        ], JSON_THROW_ON_ERROR));
        self::assertResponseIsSuccessful();
        $updatedPayload = $this->decodeJsonResponse($memberClient);

        self::assertTrue($updatedPayload['ok']);
        self::assertSame('Workspace contract v2', $updatedPayload['note']['title']);
        self::assertCount(2, $updatedPayload['note']['versions']);

        $memberClient->jsonRequest('POST', sprintf('/api/workspaces/%d/notes/%d/versions/%d/revert', $workspaceId, $noteId, $initialVersionId));
        self::assertResponseIsSuccessful();
        $revertedPayload = $this->decodeJsonResponse($memberClient);

        self::assertTrue($revertedPayload['ok']);
        self::assertSame('Workspace contract', $revertedPayload['note']['title']);
        self::assertTrue($revertedPayload['note']['isImportant']);
        self::assertCount(3, $revertedPayload['note']['versions']);
        self::assertArrayHasKey('createdAtDisplay', $revertedPayload['note']);
        self::assertArrayHasKey('recordedAtDisplay', $revertedPayload['note']['versions'][0]);

        $memberClient->request('GET', sprintf('/api/workspaces/%d', $workspaceId));
        self::assertResponseIsSuccessful();
        $workspacePayload = $this->decodeJsonResponse($memberClient);

        self::assertCount(1, $workspacePayload['notes']);
        self::assertSame($noteId, $workspacePayload['notes'][0]['id']);
        self::assertSame('Workspace contract', $workspacePayload['notes'][0]['title']);

        $memberClient->request('GET', sprintf('/api/workspaces/%d/notes/%d', $workspaceId, $noteId));
        self::assertResponseIsSuccessful();
        $notePayload = $this->decodeJsonResponse($memberClient);

        self::assertTrue($notePayload['ok']);
        self::assertSame($noteId, $notePayload['note']['id']);
        self::assertSame('Workspace contract', $notePayload['note']['title']);
        self::assertCount(3, $notePayload['note']['versions']);
    }

    public function testNotePermissionsAndMeetingModeGuardsAreEnforced(): void
    {
        $ownerEmail = sprintf('notes-owner-guard-%s@example.com', time());
        $authorEmail = sprintf('notes-author-guard-%s@example.com', time());
        $guestEmail = sprintf('notes-guest-guard-%s@example.com', time());

        $ownerClient = static::createClient();
        $this->loginWithMagicLink($ownerClient, $ownerEmail);
        $ownerClient->setServerParameter('HTTP_AUTHORIZATION', 'Bearer '.$this->createAccessTokenForEmail($ownerEmail));

        $ownerClient->jsonRequest('POST', '/api/workspaces', ['name' => 'Guard workspace']);
        self::assertResponseIsSuccessful();
        $workspaceId = $this->decodeJsonResponse($ownerClient)['workspaceId'];

        $ownerClient->jsonRequest('POST', sprintf('/api/workspaces/%d/invite', $workspaceId), ['email' => $authorEmail]);
        self::assertResponseIsSuccessful();
        $ownerClient->jsonRequest('POST', sprintf('/api/workspaces/%d/invite', $workspaceId), ['email' => $guestEmail]);
        self::assertResponseIsSuccessful();

        static::ensureKernelShutdown();
        $authorClient = static::createClient();
        $this->loginWithMagicLink($authorClient, $authorEmail);
        $authorClient->setServerParameter('HTTP_AUTHORIZATION', 'Bearer '.$this->createAccessTokenForEmail($authorEmail));
        $authorClient->jsonRequest('POST', sprintf('/api/workspaces/%d/notes', $workspaceId), [
            'title' => 'Guarded note',
            'body' => 'Protected delete path.',
        ]);
        self::assertResponseIsSuccessful();
        $noteId = (int) $this->decodeJsonResponse($authorClient)['note']['id'];

        static::ensureKernelShutdown();
        $guestClient = static::createClient();
        $this->loginWithMagicLink($guestClient, $guestEmail);
        $guestClient->setServerParameter('HTTP_AUTHORIZATION', 'Bearer '.$this->createAccessTokenForEmail($guestEmail));

        $guestClient->request('DELETE', sprintf('/api/workspaces/%d/notes/%d', $workspaceId, $noteId));
        self::assertResponseStatusCodeSame(403);

        static::ensureKernelShutdown();
        $ownerClient = static::createClient();
        $ownerClient->setServerParameter('HTTP_AUTHORIZATION', 'Bearer '.$this->createAccessTokenForEmail($ownerEmail));
        $ownerClient->request('DELETE', sprintf('/api/workspaces/%d/notes/%d', $workspaceId, $noteId));
        self::assertResponseIsSuccessful();
        self::assertFalse($this->workspaceNoteExists($noteId));

        $ownerClient->jsonRequest('POST', sprintf('/api/workspaces/%d/meeting/start', $workspaceId));
        self::assertResponseIsSuccessful();
        $ownerClient->jsonRequest('POST', sprintf('/api/workspaces/%d/notes', $workspaceId), [
            'title' => 'Blocked in meeting',
            'body' => 'Should not be allowed.',
        ]);
        self::assertResponseStatusCodeSame(403);
    }

    public function testOwnerCanTransferNoteToAnotherWorkspaceButMemberCannot(): void
    {
        $ownerEmail = sprintf('notes-transfer-owner-%s@example.com', time());
        $memberEmail = sprintf('notes-transfer-member-%s@example.com', time());

        $ownerClient = static::createClient();
        $this->loginWithMagicLink($ownerClient, $ownerEmail);
        $ownerClient->setServerParameter('HTTP_AUTHORIZATION', 'Bearer '.$this->createAccessTokenForEmail($ownerEmail));

        $ownerClient->jsonRequest('POST', '/api/workspaces', ['name' => 'Source workspace']);
        self::assertResponseIsSuccessful();
        $sourceWorkspaceId = $this->decodeJsonResponse($ownerClient)['workspaceId'];

        $ownerClient->jsonRequest('POST', '/api/workspaces', ['name' => 'Target workspace']);
        self::assertResponseIsSuccessful();
        $targetWorkspaceId = $this->decodeJsonResponse($ownerClient)['workspaceId'];

        $ownerClient->jsonRequest('POST', sprintf('/api/workspaces/%d/invite', $sourceWorkspaceId), ['email' => $memberEmail]);
        self::assertResponseIsSuccessful();

        static::ensureKernelShutdown();
        $memberClient = static::createClient();
        $this->loginWithMagicLink($memberClient, $memberEmail);
        $memberClient->setServerParameter('HTTP_AUTHORIZATION', 'Bearer '.$this->createAccessTokenForEmail($memberEmail));
        $memberClient->jsonRequest('POST', sprintf('/api/workspaces/%d/notes', $sourceWorkspaceId), [
            'title' => 'Transferable note',
            'body' => 'Move me.',
        ]);
        self::assertResponseIsSuccessful();
        $noteId = (int) $this->decodeJsonResponse($memberClient)['note']['id'];

        $memberClient->jsonRequest('POST', sprintf('/api/workspaces/%d/notes/%d/transfer', $sourceWorkspaceId, $noteId), [
            'targetWorkspaceId' => $targetWorkspaceId,
        ]);
        self::assertResponseStatusCodeSame(403);

        static::ensureKernelShutdown();
        $ownerClient = static::createClient();
        $ownerClient->setServerParameter('HTTP_AUTHORIZATION', 'Bearer '.$this->createAccessTokenForEmail($ownerEmail));
        $ownerClient->jsonRequest('POST', sprintf('/api/workspaces/%d/notes/%d/transfer', $sourceWorkspaceId, $noteId), [
            'targetWorkspaceId' => $targetWorkspaceId,
        ]);
        self::assertResponseIsSuccessful();

        $payload = $this->decodeJsonResponse($ownerClient);
        self::assertTrue($payload['ok']);
        self::assertSame($targetWorkspaceId, $payload['workspaceId']);
        self::assertSame($noteId, $payload['note']['id']);

        $ownerClient->request('GET', sprintf('/api/workspaces/%d', $sourceWorkspaceId));
        self::assertResponseIsSuccessful();
        self::assertCount(0, $this->decodeJsonResponse($ownerClient)['notes']);

        $ownerClient->request('GET', sprintf('/api/workspaces/%d', $targetWorkspaceId));
        self::assertResponseIsSuccessful();
        $targetPayload = $this->decodeJsonResponse($ownerClient);
        self::assertCount(1, $targetPayload['notes']);
        self::assertSame($noteId, $targetPayload['notes'][0]['id']);
        self::assertSame('Transferable note', $targetPayload['notes'][0]['title']);
    }

    private function workspaceNoteExists(int $noteId): bool
    {
        $note = static::getContainer()->get(EntityManagerInterface::class)
            ->getRepository(WorkspaceNote::class)
            ->find($noteId);

        return $note instanceof WorkspaceNote;
    }

    private function loginWithMagicLink(KernelBrowser $client, string $email): void
    {
        $this->clearMailpit();

        $client->jsonRequest('POST', '/api/auth/request-otp', ['email' => $email]);
        self::assertResponseIsSuccessful();
        $payload = $this->fetchSingleMailpitMessage();
        preg_match('/\b([0-9]{3})\s([0-9]{3})\b/', $payload['Text'], $match);
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
