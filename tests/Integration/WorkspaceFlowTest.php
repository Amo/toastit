<?php

namespace App\Tests\Integration;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class WorkspaceFlowTest extends WebTestCase
{
    public function testAuthenticatedUserCanCreateTeamMeetingItemAndVote(): void
    {
        $client = static::createClient();
        $mailDirectory = dirname(__DIR__, 2).'/var/storage/mails';
        array_map('unlink', glob($mailDirectory.'/*.json') ?: []);

        $email = sprintf('workspace-%s@example.com', time());

        $client->request('POST', '/connexion', ['email' => $email]);
        $payload = json_decode((string) file_get_contents((glob($mailDirectory.'/*.json') ?: [])[0]), true, 512, JSON_THROW_ON_ERROR);
        preg_match('#https?://[^\n]+/connexion/magic/[^\n]+#', $payload['text'], $magicLink);
        $client->request('GET', $magicLink[0]);
        $client->request('POST', '/pin/setup', [
            'pin' => '1234',
            'pin_confirmation' => '1234',
        ]);

        $client->request('POST', '/app', ['name' => 'Equipe Produit']);
        self::assertResponseRedirects();
        $client->followRedirect();
        self::assertSelectorTextContains('h1', 'Equipe Produit');

        $client->request('POST', '/app/teams/1/meetings', [
            'title' => 'Weekly Sync',
            'scheduled_at' => '2026-04-01T10:00',
            'is_recurring' => '1',
            'recurrence_quantity' => '1',
            'recurrence_unit' => 'week',
        ]);
        self::assertResponseRedirects('/app/teams/1');

        $client->request('POST', '/app/meetings/1/items', [
            'title' => 'Sujet prioritaire',
            'description' => 'Discussion rapide',
        ]);
        self::assertResponseRedirects('/app/meetings/1');

        $crawler = $client->request('GET', '/app/meetings/1');
        self::assertSelectorTextContains('body', 'Weekly Sync');
        self::assertSelectorTextContains('body', 'Sujet prioritaire');
        self::assertSame('0', trim($crawler->filter('.toastit-counter')->first()->text()));

        $client->xmlHttpRequest('POST', '/app/items/1/vote', [], [], [
            'HTTP_ACCEPT' => 'application/json',
        ]);

        self::assertResponseIsSuccessful();
        self::assertJsonStringEqualsJsonString(
            json_encode(['id' => 1, 'voted' => true, 'voteCount' => 1], JSON_THROW_ON_ERROR),
            (string) $client->getResponse()->getContent()
        );

        $crawler = $client->request('GET', '/app/meetings/1');
        self::assertSame('1', trim($crawler->filter('.toastit-counter')->first()->text()));
        self::assertSelectorExists('button.toastit-stepper');

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
        $client->followRedirect();
        self::assertSelectorTextContains('body', 'Retro');
        self::assertSelectorTextContains('body', 'Sujet prioritaire');
        self::assertSame('0', trim($client->getCrawler()->filter('.toastit-counter')->first()->text()));

        $client->request('POST', '/app/items/2/delete');
        self::assertResponseRedirects('/app/meetings/2');
        $client->followRedirect();
        self::assertSelectorTextContains('body', 'Le sujet a ete supprime.');
        self::assertSelectorTextContains('body', 'Aucun sujet partage pour ce meeting.');

        $client->request('POST', '/app/meetings/ad-hoc', [
            'title' => 'One to one',
            'scheduled_at' => '2026-04-03T09:30',
        ]);
        self::assertResponseRedirects('/app/meetings/3');
        $client->followRedirect();
        self::assertSelectorTextContains('body', 'One to one');

        $client->request('POST', '/app/meetings/3/invite', [
            'email' => 'guest@example.com',
        ]);
        self::assertResponseRedirects('/app/meetings/3');
        $client->followRedirect();
        self::assertSelectorTextContains('body', 'guest@example.com');
    }
}
