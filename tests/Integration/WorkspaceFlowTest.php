<?php

namespace App\Tests\Integration;

use App\Entity\Meeting;
use App\Entity\ParkingLotItem;
use App\Entity\User;
use App\Security\JwtTokenManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class WorkspaceFlowTest extends WebTestCase
{
    use MailpitTestTrait;

    public function testAuthenticatedUserCanCreateTeamMeetingItemAndVote(): void
    {
        $client = static::createClient();
        $email = sprintf('workspace-%s@example.com', time());
        $this->loginWithMagicLink($client, $email);

        $client->request('POST', '/app', ['name' => 'Equipe Produit']);
        self::assertResponseRedirects();
        $client->followRedirect();
        self::assertSelectorExists('[data-vue-root]');

        $client->request('POST', '/app/teams/1/meetings', [
            'title' => 'Weekly Sync',
            'scheduled_at' => '2026-04-01T10:00',
            'is_recurring' => '1',
            'recurrence_quantity' => '1',
            'recurrence_unit' => 'W',
        ]);
        self::assertResponseRedirects('/app/teams/1');

        $client->request('POST', '/app/meetings/1/items', [
            'title' => 'Sujet prioritaire',
            'description' => 'Discussion rapide',
        ]);
        self::assertResponseRedirects('/app/meetings/1');

        $crawler = $client->request('GET', '/app/meetings/1');
        self::assertSelectorExists('[data-vue-root]');

        $client->xmlHttpRequest('POST', '/app/items/1/vote', [], [], [
            'HTTP_ACCEPT' => 'application/json',
        ]);

        self::assertResponseIsSuccessful();
        self::assertJsonStringEqualsJsonString(
            json_encode(['id' => 1, 'voted' => true, 'voteCount' => 1], JSON_THROW_ON_ERROR),
            (string) $client->getResponse()->getContent()
        );

        $client->setServerParameter('HTTP_AUTHORIZATION', 'Bearer '.$this->createAccessTokenForEmail($email));
        $client->request('GET', '/api/meetings/1');
        self::assertResponseIsSuccessful();
        $meetingPayload = json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $client->setServerParameter('HTTP_AUTHORIZATION', '');
        self::assertSame(1, $meetingPayload['agendaItems'][0]['voteCount']);

        $client->request('POST', '/app/teams/1/meetings', [
            'title' => 'Retro',
            'scheduled_at' => '2026-04-02T11:00',
        ]);
        self::assertResponseRedirects('/app/teams/1');

        $client->request('POST', '/app/items/1/relocate', [
            'mode' => 'copy',
            'target_meeting_id' => '2',
        ]);
        self::assertResponseRedirects('/app/meetings/2');
        $client->setServerParameter('HTTP_AUTHORIZATION', 'Bearer '.$this->createAccessTokenForEmail($email));
        $client->request('GET', '/api/meetings/2');
        self::assertResponseIsSuccessful();
        $retroPayload = json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $client->setServerParameter('HTTP_AUTHORIZATION', '');
        self::assertSame('Retro', $retroPayload['meeting']['title']);
        self::assertSame('Sujet prioritaire', $retroPayload['agendaItems'][0]['title']);
        self::assertSame(0, $retroPayload['agendaItems'][0]['voteCount']);

        $client->request('POST', '/app/items/2/delete');
        self::assertResponseRedirects('/app/meetings/2');
        $client->setServerParameter('HTTP_AUTHORIZATION', 'Bearer '.$this->createAccessTokenForEmail($email));
        $client->request('GET', '/api/meetings/2');
        self::assertResponseIsSuccessful();
        $meetingAfterDeletePayload = json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $client->setServerParameter('HTTP_AUTHORIZATION', '');
        self::assertSame([], $meetingAfterDeletePayload['agendaItems']);

        $client->request('POST', '/app/meetings/ad-hoc', [
            'title' => 'One to one',
            'scheduled_at' => '2026-04-03T09:30',
        ]);
        self::assertResponseRedirects('/app/meetings/3');
        $client->setServerParameter('HTTP_AUTHORIZATION', 'Bearer '.$this->createAccessTokenForEmail($email));
        $client->request('GET', '/api/meetings/3');
        self::assertResponseIsSuccessful();
        $adHocPayload = json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $client->setServerParameter('HTTP_AUTHORIZATION', '');
        self::assertSame('One to one', $adHocPayload['meeting']['title']);

        $client->request('POST', '/app/meetings/3/invite', [
            'email' => 'guest@example.com',
        ]);
        self::assertResponseRedirects('/app/meetings/3');
        $client->setServerParameter('HTTP_AUTHORIZATION', 'Bearer '.$this->createAccessTokenForEmail($email));
        $client->request('GET', '/api/meetings/3');
        self::assertResponseIsSuccessful();
        $adHocWithInvitePayload = json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $client->setServerParameter('HTTP_AUTHORIZATION', '');
        self::assertContains('guest@example.com', array_map(static fn (array $invitee): string => $invitee['email'], $adHocWithInvitePayload['participants']['invitees']));
    }

    public function testOrganizerCanBoostAndVetoItemsAndAgendaOrderIsServerSide(): void
    {
        $client = static::createClient();
        $ownerEmail = sprintf('organizer-%s@example.com', time());
        $this->loginWithMagicLink($client, $ownerEmail);

        $client->request('POST', '/app', ['name' => 'Equipe Agenda']);
        self::assertResponseRedirects();
        $teamPath = $client->getResponse()->headers->get('Location');
        self::assertNotNull($teamPath);
        preg_match('#/app/teams/(\d+)$#', $teamPath, $teamMatches);
        $teamId = (int) ($teamMatches[1] ?? 0);

        $client->request('POST', sprintf('/app/teams/%d/meetings', $teamId), [
            'title' => 'Roadmap',
            'scheduled_at' => '2026-04-10T10:00',
        ]);
        self::assertResponseRedirects(sprintf('/app/teams/%d', $teamId));
        $meetingId = $this->findMeetingIdByTitle('Roadmap');

        $client->request('POST', sprintf('/app/meetings/%d/items', $meetingId), [
            'title' => 'Sujet A',
            'description' => 'Premier sujet',
        ]);
        self::assertResponseRedirects(sprintf('/app/meetings/%d', $meetingId));

        $client->request('POST', sprintf('/app/meetings/%d/items', $meetingId), [
            'title' => 'Sujet B',
            'description' => 'Second sujet',
        ]);
        self::assertResponseRedirects(sprintf('/app/meetings/%d', $meetingId));

        $itemAId = $this->findItemIdByTitle('Sujet A');
        $itemBId = $this->findItemIdByTitle('Sujet B');

        $client->request('POST', sprintf('/app/items/%d/vote', $itemBId));
        self::assertResponseRedirects(sprintf('/app/meetings/%d', $meetingId));

        $client->request('POST', sprintf('/app/items/%d/boost', $itemAId));
        self::assertResponseRedirects(sprintf('/app/meetings/%d', $meetingId));

        $client->request('POST', sprintf('/app/items/%d/veto', $itemBId));
        self::assertResponseRedirects(sprintf('/app/meetings/%d', $meetingId));

        $client->setServerParameter('HTTP_AUTHORIZATION', 'Bearer '.$this->createAccessTokenForEmail($ownerEmail));
        $client->request('GET', sprintf('/api/meetings/%d', $meetingId));
        self::assertResponseIsSuccessful();
        $meetingPayload = json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $client->setServerParameter('HTTP_AUTHORIZATION', '');

        self::assertSame('Sujet A', $meetingPayload['agendaItems'][0]['title']);
        self::assertTrue($meetingPayload['agendaItems'][0]['isBoosted']);
        self::assertCount(1, $meetingPayload['vetoedItems']);
        self::assertSame('Sujet B', $meetingPayload['vetoedItems'][0]['title']);
        self::assertSame(0, $meetingPayload['agendaItems'][0]['voteCount']);
    }

    public function testInvitedAdHocParticipantCannotBoostOrVeto(): void
    {
        $organizerClient = static::createClient();
        $guestEmail = sprintf('guest-%s@example.com', time());

        $this->loginWithMagicLink($organizerClient, sprintf('host-%s@example.com', time()));

        $organizerClient->request('POST', '/app/meetings/ad-hoc', [
            'title' => 'One to one',
            'scheduled_at' => '2026-04-03T09:30',
        ]);
        self::assertResponseRedirects();
        $meetingPath = $organizerClient->getResponse()->headers->get('Location');
        self::assertNotNull($meetingPath);
        preg_match('#/app/meetings/(\d+)$#', $meetingPath, $meetingMatches);
        $meetingId = (int) ($meetingMatches[1] ?? 0);

        $organizerClient->request('POST', sprintf('/app/meetings/%d/invite', $meetingId), [
            'email' => $guestEmail,
        ]);
        self::assertResponseRedirects(sprintf('/app/meetings/%d', $meetingId));

        $organizerClient->request('POST', sprintf('/app/meetings/%d/items', $meetingId), [
            'title' => 'Sujet prive',
            'description' => 'A discuter',
        ]);
        self::assertResponseRedirects(sprintf('/app/meetings/%d', $meetingId));
        $itemId = $this->findItemIdByTitle('Sujet prive');

        static::ensureKernelShutdown();
        $guestClient = static::createClient();

        $this->loginWithMagicLink($guestClient, $guestEmail);

        $guestClient->request('GET', sprintf('/app/meetings/%d', $meetingId));
        self::assertResponseIsSuccessful();

        $guestClient->request('POST', sprintf('/app/items/%d/boost', $itemId));
        self::assertResponseStatusCodeSame(403);

        $guestClient->request('POST', sprintf('/app/items/%d/veto', $itemId));
        self::assertResponseStatusCodeSame(403);
    }

    public function testOrganizerCanStartMeetingCaptureDiscussionAndCloseIt(): void
    {
        $client = static::createClient();
        $guestEmail = sprintf('follow-up-%s@example.com', time());
        $ownerEmail = sprintf('meeting-owner-%s@example.com', time());
        $this->loginWithMagicLink($client, $ownerEmail);

        $client->request('POST', '/app/meetings/ad-hoc', [
            'title' => 'Sprint review live',
            'scheduled_at' => '2026-04-12T14:00',
        ]);
        self::assertResponseRedirects();
        $meetingPath = $client->getResponse()->headers->get('Location');
        self::assertNotNull($meetingPath);
        preg_match('#/app/meetings/(\d+)$#', $meetingPath, $meetingMatches);
        $meetingId = (int) ($meetingMatches[1] ?? 0);

        $client->request('POST', sprintf('/app/meetings/%d/invite', $meetingId), [
            'email' => $guestEmail,
        ]);
        self::assertResponseRedirects(sprintf('/app/meetings/%d', $meetingId));

        $client->request('POST', sprintf('/app/meetings/%d/items', $meetingId), [
            'title' => 'Sujet suivi live',
            'description' => 'Point principal',
        ]);
        self::assertResponseRedirects(sprintf('/app/meetings/%d', $meetingId));

        $itemId = $this->findItemIdByTitle('Sujet suivi live');
        $guestUserId = $this->findUserIdByEmail($guestEmail);

        $client->request('POST', sprintf('/app/meetings/%d/start', $meetingId));
        self::assertResponseRedirects(sprintf('/app/meetings/%d', $meetingId));

        $client->request('POST', sprintf('/app/items/%d/discussion', $itemId), [
            'discussion_status' => 'treated',
            'discussion_notes' => 'Discussion finalisee pendant le live.',
            'follow_up_titles' => ['Envoyer le recap et preparer la suite.'],
            'follow_up_owner_ids' => [(string) $guestUserId],
            'follow_up_due_on' => ['2026-04-15'],
            'owner_id' => (string) $guestUserId,
            'due_at' => '2026-04-15',
        ]);
        self::assertResponseRedirects(sprintf('/app/meetings/%d', $meetingId));

        $crawler = $client->request('GET', sprintf('/app/meetings/%d', $meetingId));
        self::assertSelectorExists('[data-vue-root]');

        $client->setServerParameter('HTTP_AUTHORIZATION', 'Bearer '.$this->createAccessTokenForEmail($ownerEmail));
        $client->request('GET', sprintf('/api/meetings/%d', $meetingId));
        self::assertResponseIsSuccessful();
        $meetingPayload = json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $client->setServerParameter('HTTP_AUTHORIZATION', '');
        self::assertSame('live', $meetingPayload['meeting']['status']);
        self::assertSame('treated', $meetingPayload['agendaItems'][0]['discussionStatus']);
        self::assertSame('Discussion finalisee pendant le live.', $meetingPayload['agendaItems'][0]['discussionNotes']);
        self::assertSame('Envoyer le recap et preparer la suite.', $meetingPayload['agendaItems'][0]['followUpItems'][0]['title']);
        self::assertSame($guestUserId, $meetingPayload['agendaItems'][0]['followUpItems'][0]['ownerId']);
        self::assertSame('2026-04-15', $meetingPayload['agendaItems'][0]['followUpItems'][0]['dueOn']);
        self::assertSame($guestEmail, $meetingPayload['agendaItems'][0]['owner']['displayName']);
        self::assertSame('15/04/2026', $meetingPayload['agendaItems'][0]['dueOnDisplay']);

        $client->request('POST', sprintf('/app/meetings/%d/close', $meetingId));
        self::assertResponseRedirects(sprintf('/app/meetings/%d', $meetingId));

        $client->setServerParameter('HTTP_AUTHORIZATION', 'Bearer '.$this->createAccessTokenForEmail($ownerEmail));
        $client->request('GET', sprintf('/api/meetings/%d', $meetingId));
        self::assertResponseIsSuccessful();
        $closedMeetingPayload = json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $client->setServerParameter('HTTP_AUTHORIZATION', '');
        self::assertSame('closed', $closedMeetingPayload['meeting']['status']);

        $client->request('POST', sprintf('/app/items/%d/vote', $itemId));
        self::assertResponseStatusCodeSame(403);
    }

    public function testClosingRecurringMeetingCreatesNextOccurrence(): void
    {
        $client = static::createClient();
        $ownerEmail = sprintf('recurring-owner-%s@example.com', time());
        $this->loginWithMagicLink($client, $ownerEmail);

        $client->request('POST', '/app', ['name' => 'Equipe Recurrence']);
        self::assertResponseRedirects();
        $teamPath = $client->getResponse()->headers->get('Location');
        self::assertNotNull($teamPath);
        preg_match('#/app/teams/(\d+)$#', $teamPath, $teamMatches);
        $teamId = (int) ($teamMatches[1] ?? 0);

        $client->request('POST', sprintf('/app/teams/%d/meetings', $teamId), [
            'title' => 'Weekly planning',
            'scheduled_at' => '2026-04-07T09:00',
            'is_recurring' => '1',
            'recurrence_quantity' => '1',
            'recurrence_unit' => 'W',
        ]);
        self::assertResponseRedirects(sprintf('/app/teams/%d', $teamId));

        $meetingId = $this->findMeetingIdByTitle('Weekly planning');

        $client->request('POST', sprintf('/app/meetings/%d/items', $meetingId), [
            'title' => 'Sujet source',
            'description' => 'Point de depart',
        ]);
        self::assertResponseRedirects(sprintf('/app/meetings/%d', $meetingId));

        $itemId = $this->findItemIdByTitle('Sujet source');

        $client->request('POST', sprintf('/app/items/%d/discussion', $itemId), [
            'discussion_status' => 'postponed',
            'discussion_notes' => 'Deux actions a reporter.',
            'follow_up_titles' => ['Preparer le draft', 'Partager la synthese'],
            'follow_up_owner_ids' => ['', ''],
            'follow_up_due_on' => ['2026-04-14', '2026-04-20'],
            'owner_id' => '',
            'due_at' => '',
        ]);
        self::assertResponseRedirects(sprintf('/app/meetings/%d', $meetingId));

        $client->request('POST', sprintf('/app/meetings/%d/close', $meetingId));
        self::assertResponseRedirects(sprintf('/app/meetings/%d', $meetingId));

        $client->setServerParameter('HTTP_AUTHORIZATION', 'Bearer '.$this->createAccessTokenForEmail($ownerEmail));
        $client->request('GET', sprintf('/api/teams/%d', $teamId));
        self::assertResponseIsSuccessful();
        $teamPayload = json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $client->setServerParameter('HTTP_AUTHORIZATION', '');

        $teamMeetingTitles = array_map(static fn (array $meeting): string => $meeting['title'], $teamPayload['meetings']);
        self::assertContains('Weekly planning', $teamMeetingTitles);
        $nextMeetingSummary = array_values(array_filter($teamPayload['meetings'], static fn (array $meeting): bool => $meeting['scheduledAtDisplay'] === '14/04/2026 09:00'));
        self::assertNotEmpty($nextMeetingSummary);

        $meetings = static::getContainer()->get(EntityManagerInterface::class)
            ->getRepository(Meeting::class)
            ->findBy(['title' => 'Weekly planning'], ['scheduledAt' => 'ASC']);

        self::assertCount(2, $meetings);
        self::assertSame('2026-04-07 09:00', $meetings[0]->getScheduledAt()->format('Y-m-d H:i'));
        self::assertSame('2026-04-14 09:00', $meetings[1]->getScheduledAt()->format('Y-m-d H:i'));

        $client->setServerParameter('HTTP_AUTHORIZATION', 'Bearer '.$this->createAccessTokenForEmail($ownerEmail));
        $client->request('GET', sprintf('/api/meetings/%d', $meetings[1]->getId()));
        self::assertResponseIsSuccessful();
        $nextMeetingPayload = json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $client->setServerParameter('HTTP_AUTHORIZATION', '');
        $nextMeetingTitles = array_map(static fn (array $item): string => $item['title'], $nextMeetingPayload['agendaItems']);
        self::assertContains('Preparer le draft', $nextMeetingTitles);
        self::assertContains('Partager la synthese', $nextMeetingTitles);
    }

    public function testNewTeamMemberIsAddedToAllNonClosedTeamMeetings(): void
    {
        $client = static::createClient();
        $futureMemberEmail = sprintf('team-member-%s@example.com', time());
        $ownerEmail = sprintf('team-owner-%s@example.com', time());
        $this->loginWithMagicLink($client, $ownerEmail);

        $client->request('POST', '/app', ['name' => 'Equipe Delivery']);
        self::assertResponseRedirects();
        $teamPath = $client->getResponse()->headers->get('Location');
        self::assertNotNull($teamPath);
        preg_match('#/app/teams/(\d+)$#', $teamPath, $teamMatches);
        $teamId = (int) ($teamMatches[1] ?? 0);

        $client->request('POST', sprintf('/app/teams/%d/meetings', $teamId), [
            'title' => 'Future meeting',
            'scheduled_at' => '2026-04-21T10:00',
        ]);
        self::assertResponseRedirects(sprintf('/app/teams/%d', $teamId));
        $futureMeetingId = $this->findMeetingIdByTitle('Future meeting');

        $client->request('POST', sprintf('/app/teams/%d/meetings', $teamId), [
            'title' => 'Closed meeting',
            'scheduled_at' => '2026-04-10T09:00',
        ]);
        self::assertResponseRedirects(sprintf('/app/teams/%d', $teamId));
        $closedMeetingId = $this->findMeetingIdByTitle('Closed meeting');

        $client->request('POST', sprintf('/app/meetings/%d/close', $closedMeetingId));
        self::assertResponseRedirects(sprintf('/app/meetings/%d', $closedMeetingId));

        $client->request('POST', sprintf('/app/teams/%d/invite', $teamId), [
            'email' => $futureMemberEmail,
        ]);
        self::assertResponseRedirects(sprintf('/app/teams/%d', $teamId));

        $client->setServerParameter('HTTP_AUTHORIZATION', 'Bearer '.$this->createAccessTokenForEmail($ownerEmail));
        $client->request('GET', sprintf('/api/meetings/%d', $futureMeetingId));
        self::assertResponseIsSuccessful();
        $futureMeetingPayload = json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertContains($futureMemberEmail, array_map(static fn (array $invitee): string => $invitee['email'], $futureMeetingPayload['participants']['invitees']));

        $client->request('GET', sprintf('/api/meetings/%d', $closedMeetingId));
        self::assertResponseIsSuccessful();
        $closedMeetingPayload = json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $client->setServerParameter('HTTP_AUTHORIZATION', '');
        self::assertNotContains($futureMemberEmail, array_map(static fn (array $invitee): string => $invitee['email'], $closedMeetingPayload['participants']['invitees']));
    }

    public function testCreatingTeamMeetingAddsExistingTeamMembersAsParticipants(): void
    {
        $client = static::createClient();
        $memberEmail = sprintf('autofill-member-%s@example.com', time());
        $ownerEmail = sprintf('team-owner-autofill-%s@example.com', time());
        $this->loginWithMagicLink($client, $ownerEmail);

        $client->request('POST', '/app', ['name' => 'Equipe Autofill']);
        self::assertResponseRedirects();
        $teamPath = $client->getResponse()->headers->get('Location');
        self::assertNotNull($teamPath);
        preg_match('#/app/teams/(\d+)$#', $teamPath, $teamMatches);
        $teamId = (int) ($teamMatches[1] ?? 0);

        $client->request('POST', sprintf('/app/teams/%d/invite', $teamId), [
            'email' => $memberEmail,
        ]);
        self::assertResponseRedirects(sprintf('/app/teams/%d', $teamId));

        $client->request('POST', sprintf('/app/teams/%d/meetings', $teamId), [
            'title' => 'Meeting equipe complet',
            'scheduled_at' => '2026-04-25T11:00',
        ]);
        self::assertResponseRedirects(sprintf('/app/teams/%d', $teamId));

        $meetingId = $this->findMeetingIdByTitle('Meeting equipe complet');

        $client->setServerParameter('HTTP_AUTHORIZATION', 'Bearer '.$this->createAccessTokenForEmail($ownerEmail));
        $client->request('GET', sprintf('/api/meetings/%d', $meetingId));
        self::assertResponseIsSuccessful();
        $meetingPayload = json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $client->setServerParameter('HTTP_AUTHORIZATION', '');

        self::assertContains($memberEmail, array_map(static fn (array $invitee): string => $invitee['email'], $meetingPayload['participants']['invitees']));
    }

    public function testRemovingTeamMemberRemovesThemFromAllNonClosedTeamMeetings(): void
    {
        $client = static::createClient();
        $memberEmail = sprintf('removable-member-%s@example.com', time());
        $ownerEmail = sprintf('team-owner-remove-%s@example.com', time());
        $this->loginWithMagicLink($client, $ownerEmail);

        $client->request('POST', '/app', ['name' => 'Equipe Ops']);
        self::assertResponseRedirects();
        $teamPath = $client->getResponse()->headers->get('Location');
        self::assertNotNull($teamPath);
        preg_match('#/app/teams/(\d+)$#', $teamPath, $teamMatches);
        $teamId = (int) ($teamMatches[1] ?? 0);

        $client->request('POST', sprintf('/app/teams/%d/meetings', $teamId), [
            'title' => 'Open meeting',
            'scheduled_at' => '2026-04-22T10:00',
        ]);
        self::assertResponseRedirects(sprintf('/app/teams/%d', $teamId));
        $openMeetingId = $this->findMeetingIdByTitle('Open meeting');

        $client->request('POST', sprintf('/app/teams/%d/meetings', $teamId), [
            'title' => 'Already closed meeting',
            'scheduled_at' => '2026-04-11T09:00',
        ]);
        self::assertResponseRedirects(sprintf('/app/teams/%d', $teamId));
        $closedMeetingId = $this->findMeetingIdByTitle('Already closed meeting');

        $client->request('POST', sprintf('/app/meetings/%d/close', $closedMeetingId));
        self::assertResponseRedirects(sprintf('/app/meetings/%d', $closedMeetingId));

        $client->request('POST', sprintf('/app/teams/%d/invite', $teamId), [
            'email' => $memberEmail,
        ]);
        self::assertResponseRedirects(sprintf('/app/teams/%d', $teamId));

        $client->setServerParameter('HTTP_AUTHORIZATION', 'Bearer '.$this->createAccessTokenForEmail($ownerEmail));
        $client->request('GET', sprintf('/api/meetings/%d', $openMeetingId));
        self::assertResponseIsSuccessful();
        $openMeetingPayload = json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertContains($memberEmail, array_map(static fn (array $invitee): string => $invitee['email'], $openMeetingPayload['participants']['invitees']));
        $client->setServerParameter('HTTP_AUTHORIZATION', '');

        $memberId = $this->findTeamMembershipIdByEmail($teamId, $memberEmail);

        $client->request('POST', sprintf('/app/teams/%d/members/%d/remove', $teamId, $memberId));
        self::assertResponseRedirects(sprintf('/app/teams/%d', $teamId));
        $client->request('GET', sprintf('/app/teams/%d', $teamId));

        $client->setServerParameter('HTTP_AUTHORIZATION', 'Bearer '.$this->createAccessTokenForEmail($ownerEmail));
        $client->request('GET', sprintf('/api/teams/%d', $teamId));
        self::assertResponseIsSuccessful();
        $teamPayload = json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $client->setServerParameter('HTTP_AUTHORIZATION', '');
        $memberEmails = array_map(static fn (array $membership): string => $membership['user']['email'], $teamPayload['memberships']);
        self::assertNotContains($memberEmail, $memberEmails);
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

    private function findMeetingIdByTitle(string $title): int
    {
        $meeting = static::getContainer()->get(EntityManagerInterface::class)
            ->getRepository(Meeting::class)
            ->findOneBy(['title' => $title]);

        self::assertInstanceOf(Meeting::class, $meeting);

        return $meeting->getId();
    }

    private function findItemIdByTitle(string $title): int
    {
        $item = static::getContainer()->get(EntityManagerInterface::class)
            ->getRepository(ParkingLotItem::class)
            ->findOneBy(['title' => $title]);

        self::assertInstanceOf(ParkingLotItem::class, $item);

        return $item->getId();
    }

    private function findUserIdByEmail(string $email): int
    {
        $user = static::getContainer()->get(EntityManagerInterface::class)
            ->getRepository(\App\Entity\User::class)
            ->findOneBy(['email' => $email]);

        self::assertInstanceOf(\App\Entity\User::class, $user);

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

    private function findTeamMembershipIdByEmail(int $teamId, string $email): int
    {
        $membership = static::getContainer()->get(EntityManagerInterface::class)
            ->createQuery('SELECT membership FROM App\Entity\TeamMember membership JOIN membership.user user WHERE membership.team = :teamId AND user.email = :email')
            ->setParameter('teamId', $teamId)
            ->setParameter('email', $email)
            ->getOneOrNullResult();

        self::assertInstanceOf(\App\Entity\TeamMember::class, $membership);

        return $membership->getId();
    }
}
