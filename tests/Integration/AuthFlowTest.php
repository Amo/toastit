<?php

namespace App\Tests\Integration;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class AuthFlowTest extends WebTestCase
{
    public function testRequestLoginCreatesStoredMailAndRedirectsToOtpPage(): void
    {
        $client = static::createClient();
        $mailDirectory = dirname(__DIR__, 2).'/var/storage/mails';
        array_map('unlink', glob($mailDirectory.'/*.json') ?: []);
        $email = sprintf('integration-%s@example.com', time());

        $client->request('POST', '/connexion', ['email' => $email]);

        self::assertResponseRedirects('/connexion/verifier?email='.$email.'&purpose=login');

        $files = glob($mailDirectory.'/*.json') ?: [];
        self::assertCount(1, $files);

        $payload = json_decode((string) file_get_contents($files[0]), true, 512, JSON_THROW_ON_ERROR);

        self::assertSame('Votre code de connexion Toastit', $payload['subject']);
        self::assertStringContainsString('/connexion/magic/', $payload['text']);
        self::assertMatchesRegularExpression('/\n[A-Z0-9]{6}\n\nCe code expire/', $payload['text']);
    }

    public function testMagicLinkForExistingPinnedUserRedirectsToPinUnlock(): void
    {
        $client = static::createClient();
        $mailDirectory = dirname(__DIR__, 2).'/var/storage/mails';
        array_map('unlink', glob($mailDirectory.'/*.json') ?: []);

        $email = sprintf('existing-%s@example.com', time());

        $client->request('POST', '/connexion', ['email' => $email]);
        $firstPayload = json_decode((string) file_get_contents((glob($mailDirectory.'/*.json') ?: [])[0]), true, 512, JSON_THROW_ON_ERROR);
        preg_match('#https?://[^\n]+/connexion/magic/[^\n]+#', $firstPayload['text'], $firstLink);
        $client->request('GET', $firstLink[0]);
        $client->request('POST', '/pin/setup', [
            'pin' => '1234',
            'pin_confirmation' => '1234',
        ]);
        $client->request('POST', '/logout');

        array_map('unlink', glob($mailDirectory.'/*.json') ?: []);

        $client->request('POST', '/connexion', ['email' => $email]);
        $secondPayload = json_decode((string) file_get_contents((glob($mailDirectory.'/*.json') ?: [])[0]), true, 512, JSON_THROW_ON_ERROR);
        preg_match('#https?://[^\n]+/connexion/magic/[^\n]+#', $secondPayload['text'], $secondLink);
        $client->request('GET', $secondLink[0]);

        self::assertResponseRedirects('/pin/unlock');
    }
}
