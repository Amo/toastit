<?php

namespace App\Tests\Integration;

use App\Entity\Toast;
use App\Entity\User;
use App\Entity\Workspace;
use App\Entity\WorkspaceMember;
use App\Security\JwtTokenService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

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

        self::assertSame('treated', $payload['resolvedItems'][0]['discussionStatus']);
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

    private function loginWithMagicLink(KernelBrowser $client, string $email): void
    {
        $this->clearMailpit();

        $client->request('POST', '/connexion', ['email' => $email]);
        $payload = $this->fetchSingleMailpitMessage();
        preg_match('#https?://[^\r\n]+/connexion/magic/[^\r\n]+#', $payload['Text'], $magicLink);
        $client->request('GET', trim($magicLink[0]));
        $client->request('POST', '/pin/setup', [
            'pin' => '1234',
            'pin_confirmation' => '1234',
        ]);
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
