<?php

namespace App\Tests\Integration;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class AuthFlowTest extends WebTestCase
{
    use MailpitTestTrait;

    public function testRequestLoginCreatesStoredMailAndRedirectsToOtpPage(): void
    {
        $client = static::createClient();
        $this->clearMailpit();
        $email = sprintf('integration-%s@example.com', time());

        $client->request('POST', '/connexion', ['email' => $email]);

        self::assertResponseRedirects('/connexion/verifier?email='.$email.'&purpose=login');

        $payload = $this->fetchSingleMailpitMessage();

        self::assertSame('Votre code de connexion Toastit', $payload['Subject']);
        self::assertStringContainsString('/connexion/magic/', $payload['Text']);
        self::assertMatchesRegularExpression('/\R[A-Z0-9]{6}\R\RCe code expire/', $payload['Text']);
    }

    public function testMagicLinkForExistingPinnedUserRedirectsToPinUnlock(): void
    {
        $client = static::createClient();
        $this->clearMailpit();

        $email = sprintf('existing-%s@example.com', time());

        $client->request('POST', '/connexion', ['email' => $email]);
        $firstPayload = $this->fetchSingleMailpitMessage();
        preg_match('#https?://[^\r\n]+/connexion/magic/[^\r\n]+#', $firstPayload['Text'], $firstLink);
        $client->request('GET', trim($firstLink[0]));
        $client->request('POST', '/pin/setup', [
            'pin' => '1234',
            'pin_confirmation' => '1234',
        ]);
        $client->request('POST', '/logout');

        $this->clearMailpit();

        $client->request('POST', '/connexion', ['email' => $email]);
        $secondPayload = $this->fetchSingleMailpitMessage();
        preg_match('#https?://[^\r\n]+/connexion/magic/[^\r\n]+#', $secondPayload['Text'], $secondLink);
        $client->request('GET', trim($secondLink[0]));

        self::assertResponseRedirects('/pin/unlock');
    }
}
