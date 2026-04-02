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

        $client->request('POST', '/app', ['name' => 'Produit']);
        $workspaceId = $this->assertWorkspaceRedirectAndReturnId($client);

        $title = sprintf('Sujet prioritaire %s', microtime(true));

        $client->request('POST', sprintf('/app/workspaces/%d/items', $workspaceId), [
            'title' => $title,
            'description' => 'Discussion rapide',
        ]);
        self::assertResponseRedirects(sprintf('/app/workspaces/%d', $workspaceId));

        $itemId = $this->findToastIdByTitle($title);

        $client->xmlHttpRequest('POST', sprintf('/app/items/%d/vote', $itemId), [], [], [
            'HTTP_ACCEPT' => 'application/json',
        ]);
        self::assertResponseIsSuccessful();

        $client->setServerParameter('HTTP_AUTHORIZATION', 'Bearer '.$this->createAccessTokenForEmail($email));
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

        $client->request('POST', '/app', ['name' => 'Delivery']);
        $workspaceId = $this->assertWorkspaceRedirectAndReturnId($client);

        $client->request('POST', sprintf('/app/workspaces/%d/invite', $workspaceId), ['email' => $memberEmail]);
        self::assertResponseRedirects(sprintf('/app/workspaces/%d', $workspaceId));

        $client->request('POST', sprintf('/app/workspaces/%d/meeting/start', $workspaceId));
        self::assertResponseRedirects(sprintf('/app/workspaces/%d', $workspaceId));

        $client->setServerParameter('HTTP_AUTHORIZATION', 'Bearer '.$this->createAccessTokenForEmail($ownerEmail));
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

        $client->request('POST', '/app', ['name' => 'Ops']);
        $workspaceId = $this->assertWorkspaceRedirectAndReturnId($client);
        $sourceTitle = sprintf('Sujet source %s', microtime(true));
        $client->request('POST', sprintf('/app/workspaces/%d/invite', $workspaceId), ['email' => $memberEmail]);
        $client->request('POST', sprintf('/app/workspaces/%d/items', $workspaceId), [
            'title' => $sourceTitle,
            'description' => 'Point de depart',
        ]);
        $client->request('POST', sprintf('/app/workspaces/%d/meeting/start', $workspaceId));

        $memberUserId = $this->findUserIdByEmail($memberEmail);
        $sourceToastId = $this->findToastIdByTitle($sourceTitle);

        $followUpTitle = sprintf('Envoyer le recap %s', microtime(true));
        $client->request('POST', sprintf('/app/items/%d/discussion', $sourceToastId), [
            'discussion_status' => 'treated',
            'discussion_notes' => 'Discussion finalisee.',
            'follow_up_titles' => [$followUpTitle],
            'follow_up_owner_ids' => [(string) $memberUserId],
            'follow_up_due_on' => ['2026-04-15'],
            'owner_id' => (string) $memberUserId,
            'due_at' => '2026-04-15',
        ]);
        self::assertResponseRedirects(sprintf('/app/workspaces/%d', $workspaceId));

        $client->setServerParameter('HTTP_AUTHORIZATION', 'Bearer '.$this->createAccessTokenForEmail($ownerEmail));
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

        $ownerClient->request('POST', '/app', ['name' => 'Review']);
        $workspaceId = $this->assertWorkspaceRedirectAndReturnId($ownerClient);
        $title = sprintf('Sujet prive %s', microtime(true));
        $ownerClient->request('POST', sprintf('/app/workspaces/%d/invite', $workspaceId), ['email' => $guestEmail]);
        $ownerClient->request('POST', sprintf('/app/workspaces/%d/items', $workspaceId), [
            'title' => $title,
            'description' => 'A discuter',
        ]);
        $ownerClient->request('POST', sprintf('/app/workspaces/%d/meeting/start', $workspaceId));
        $itemId = $this->findToastIdByTitle($title);

        static::ensureKernelShutdown();
        $guestClient = static::createClient();
        $this->loginWithMagicLink($guestClient, $guestEmail);

        $guestClient->request('POST', sprintf('/app/items/%d/boost', $itemId));
        self::assertResponseStatusCodeSame(403);

        $guestClient->request('POST', sprintf('/app/items/%d/veto', $itemId));
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

    private function assertWorkspaceRedirectAndReturnId(KernelBrowser $client): int
    {
        self::assertResponseRedirects();
        $target = $client->getResponse()->headers->get('Location');
        self::assertMatchesRegularExpression('#^/app/workspaces/\d+$#', (string) $target);
        preg_match('#/app/workspaces/(\d+)$#', (string) $target, $matches);

        return (int) $matches[1];
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
