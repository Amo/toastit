<?php

namespace App\Tests\Integration;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class PinFlowTest extends WebTestCase
{
    use MailpitTestTrait;

    public function testOtpAndPinSetupUnlockProtectedApp(): void
    {
        $client = static::createClient();
        $this->clearMailpit();

        $email = sprintf('pin-%s@example.com', time());

        $client->request('POST', '/connexion', ['email' => $email]);
        $payload = $this->fetchSingleMailpitMessage();
        preg_match('/\R([A-Z0-9]{6})\R\RCe code expire/', $payload['Text'], $match);
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
        self::assertSelectorExists('[data-vue-root]');
    }
}
