<?php

namespace App\Tests\Integration;

use App\Entity\User;
use App\Security\JwtTokenService;
use App\Workspace\InboundEmailAddressService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class InboxWorkspaceFlowTest extends WebTestCase
{
    use MailpitTestTrait;

    public function testInboxWorkspaceIsCreatedOnDemandHiddenAndReceivesInboundEmail(): void
    {
        $client = static::createClient();
        $email = sprintf('inbox-%s@example.com', time());
        $this->loginWithMagicLink($client, $email);
        $client->setServerParameter('HTTP_AUTHORIZATION', 'Bearer '.$this->createAccessTokenForEmail($email));

        $client->request('GET', '/api/dashboard');
        self::assertResponseIsSuccessful();
        $dashboardPayload = $this->decodeJsonResponse($client);
        self::assertSame([], array_values(array_filter(
            $dashboardPayload['workspaces'],
            static fn (array $workspace): bool => 'Inbox' === $workspace['name'],
        )));

        $client->request('GET', '/api/inbox/workspace');
        self::assertResponseIsSuccessful();
        $inboxPayload = $this->decodeJsonResponse($client);
        $workspaceId = (int) $inboxPayload['workspace']['id'];

        self::assertTrue($inboxPayload['workspace']['isInboxWorkspace']);
        self::assertTrue($inboxPayload['workspace']['isSoloWorkspace']);
        self::assertSame('Inbox', $inboxPayload['workspace']['name']);

        $user = $this->findUserByEmail($email);
        $recipient = static::getContainer()->get(InboundEmailAddressService::class)->buildAddressForUser($user);
        self::assertNotNull($recipient);
        self::assertSame($recipient, $inboxPayload['currentUser']['inboxEmailAddress']);
        self::assertSame(mb_strtolower($recipient), $recipient);

        $client->setServerParameter('HTTP_AUTHORIZATION', '');
        $client->request('POST', '/api/inbound/email', server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_TOASTIT_INBOUND_SECRET' => 'test-inbound-secret',
        ], content: json_encode([
            'recipient' => $recipient,
            'from' => 'sender@example.com',
            'subject' => 'Inbox-created toast',
            'text' => "This email should become a toast.\n\nWith the mail body kept as description.",
        ], JSON_THROW_ON_ERROR));
        self::assertResponseIsSuccessful();
        $ingestPayload = $this->decodeJsonResponse($client);
        self::assertSame($workspaceId, $ingestPayload['workspaceId']);

        $client->setServerParameter('HTTP_AUTHORIZATION', 'Bearer '.$this->createAccessTokenForEmail($email));
        $client->request('GET', '/api/dashboard');
        self::assertResponseIsSuccessful();
        $dashboardPayload = $this->decodeJsonResponse($client);
        self::assertNotContains($workspaceId, array_column($dashboardPayload['workspaces'], 'id'));

        $client->request('GET', '/api/inbox/workspace');
        self::assertResponseIsSuccessful();
        $inboxPayload = $this->decodeJsonResponse($client);
        self::assertSame('Inbox-created toast', $inboxPayload['agendaItems'][0]['title']);
        self::assertStringContainsString('sender@example.com', (string) $inboxPayload['agendaItems'][0]['description']);

        $client->jsonRequest('POST', sprintf('/api/workspaces/%d/invite', $workspaceId), ['email' => 'guest@example.com']);
        self::assertResponseStatusCodeSame(400);
        self::assertSame('inbox_workspace_not_shareable', $this->decodeJsonResponse($client)['error']);

        $client->jsonRequest('POST', sprintf('/api/workspaces/%d/settings', $workspaceId), ['name' => 'Renamed inbox']);
        self::assertResponseStatusCodeSame(400);
        self::assertSame('inbox_workspace_not_configurable', $this->decodeJsonResponse($client)['error']);

        $client->request('DELETE', sprintf('/api/workspaces/%d', $workspaceId));
        self::assertResponseStatusCodeSame(400);
        self::assertSame('inbox_workspace_not_deletable', $this->decodeJsonResponse($client)['error']);
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
        $verifyPayload = $this->decodeJsonResponse($client);
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
        return static::getContainer()->get(JwtTokenService::class)
            ->createAccessToken($this->findUserByEmail($email), new \DateTimeImmutable());
    }

    private function findUserByEmail(string $email): User
    {
        $user = static::getContainer()->get(EntityManagerInterface::class)
            ->getRepository(User::class)
            ->findOneBy(['email' => $email]);

        self::assertInstanceOf(User::class, $user);

        return $user;
    }
}
