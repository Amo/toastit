<?php

namespace App\Tests\Integration;

use App\Entity\Toast;
use App\Entity\User;
use App\Entity\Workspace;
use App\Entity\WorkspaceMember;
use App\Security\JwtTokenManager;
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
        self::assertResponseRedirects('/app/workspaces/1');

        $client->request('POST', '/app/workspaces/1/items', [
            'title' => 'Sujet prioritaire',
            'description' => 'Discussion rapide',
        ]);
        self::assertResponseRedirects('/app/workspaces/1');

        $client->xmlHttpRequest('POST', '/app/items/1/vote', [], [], [
            'HTTP_ACCEPT' => 'application/json',
        ]);
        self::assertResponseIsSuccessful();

        $client->setServerParameter('HTTP_AUTHORIZATION', 'Bearer '.$this->createAccessTokenForEmail($email));
        $client->request('GET', '/api/workspaces/1');
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
        self::assertResponseRedirects('/app/workspaces/1');

        $client->request('POST', '/app/workspaces/1/invite', ['email' => $memberEmail]);
        self::assertResponseRedirects('/app/workspaces/1');

        $client->request('POST', '/app/workspaces/1/meeting/start');
        self::assertResponseRedirects('/app/workspaces/1');

        $client->setServerParameter('HTTP_AUTHORIZATION', 'Bearer '.$this->createAccessTokenForEmail($ownerEmail));
        $client->request('GET', '/api/workspaces/1');
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
        $client->request('POST', '/app/workspaces/1/invite', ['email' => $memberEmail]);
        $client->request('POST', '/app/workspaces/1/items', [
            'title' => 'Sujet source',
            'description' => 'Point de depart',
        ]);
        $client->request('POST', '/app/workspaces/1/meeting/start');

        $memberUserId = $this->findUserIdByEmail($memberEmail);

        $client->request('POST', '/app/items/1/discussion', [
            'discussion_status' => 'treated',
            'discussion_notes' => 'Discussion finalisee.',
            'follow_up_titles' => ['Envoyer le recap'],
            'follow_up_owner_ids' => [(string) $memberUserId],
            'follow_up_due_on' => ['2026-04-15'],
            'owner_id' => (string) $memberUserId,
            'due_at' => '2026-04-15',
        ]);
        self::assertResponseRedirects('/app/workspaces/1');

        $client->setServerParameter('HTTP_AUTHORIZATION', 'Bearer '.$this->createAccessTokenForEmail($ownerEmail));
        $client->request('GET', '/api/workspaces/1');
        self::assertResponseIsSuccessful();
        $payload = json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $client->setServerParameter('HTTP_AUTHORIZATION', '');

        self::assertSame('treated', $payload['resolvedItems'][0]['discussionStatus']);
        self::assertSame('Envoyer le recap', $payload['resolvedItems'][0]['followUpItems'][0]['title']);

        $followUp = static::getContainer()->get(EntityManagerInterface::class)
            ->getRepository(Toast::class)
            ->findOneBy(['title' => 'Envoyer le recap']);

        self::assertInstanceOf(Toast::class, $followUp);
        self::assertSame('Sujet source', $followUp->getPreviousItem()?->getTitle());
        self::assertSame(1, $followUp->getWorkspace()->getId());
    }

    public function testInvitedMemberCannotBoostOutsideOrganizerPermissions(): void
    {
        $ownerClient = static::createClient();
        $ownerEmail = sprintf('host-%s@example.com', time());
        $guestEmail = sprintf('guest-%s@example.com', time());
        $this->loginWithMagicLink($ownerClient, $ownerEmail);

        $ownerClient->request('POST', '/app', ['name' => 'Review']);
        $ownerClient->request('POST', '/app/workspaces/1/invite', ['email' => $guestEmail]);
        $ownerClient->request('POST', '/app/workspaces/1/items', [
            'title' => 'Sujet prive',
            'description' => 'A discuter',
        ]);
        $ownerClient->request('POST', '/app/workspaces/1/meeting/start');

        static::ensureKernelShutdown();
        $guestClient = static::createClient();
        $this->loginWithMagicLink($guestClient, $guestEmail);

        $guestClient->request('POST', '/app/items/1/boost');
        self::assertResponseStatusCodeSame(403);

        $guestClient->request('POST', '/app/items/1/veto');
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

    private function createAccessTokenForEmail(string $email): string
    {
        $user = static::getContainer()->get(EntityManagerInterface::class)
            ->getRepository(User::class)
            ->findOneBy(['email' => $email]);

        self::assertInstanceOf(User::class, $user);

        return static::getContainer()->get(JwtTokenManager::class)
            ->createAccessToken($user, new \DateTimeImmutable());
    }
}
