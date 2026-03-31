<?php

namespace App\Tests\Integration;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class PinFlowTest extends WebTestCase
{
    public function testOtpAndPinSetupUnlockProtectedApp(): void
    {
        $client = static::createClient();
        $mailDirectory = dirname(__DIR__, 2).'/var/storage/mails';
        array_map('unlink', glob($mailDirectory.'/*.json') ?: []);

        $email = sprintf('pin-%s@example.com', time());

        $client->request('POST', '/connexion', ['email' => $email]);
        $payload = json_decode((string) file_get_contents((glob($mailDirectory.'/*.json') ?: [])[0]), true, 512, JSON_THROW_ON_ERROR);
        preg_match('/\n([A-Z0-9]{6})\n\nCe code expire/', $payload['text'], $match);
        $code = $match[1];

        $client->request('POST', '/connexion/verifier', [
            'email' => $email,
            'purpose' => 'login',
            'code' => $code,
        ]);
        self::assertResponseRedirects('/pin/setup');

        $client->request('POST', '/pin/setup', [
            'pin' => '1234',
            'pin_confirmation' => '1234',
        ]);
        self::assertResponseRedirects('/app');

        $client->request('GET', '/app');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Session deverrouillee.');
    }
}
