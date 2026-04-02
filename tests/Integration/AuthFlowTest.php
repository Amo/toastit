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

        $client->jsonRequest('POST', '/api/auth/request-otp', ['email' => $email]);
        self::assertResponseIsSuccessful();

        $payload = $this->fetchSingleMailpitMessage();

        self::assertSame('Votre code de connexion Toastit', $payload['Subject']);
        self::assertStringContainsString('/connexion/magic/', $payload['Text']);
        self::assertMatchesRegularExpression('/\R[0-9]{3} [0-9]{3}\R\RCe code expire/', $payload['Text']);
    }

    public function testMagicLinkForExistingPinnedUserRedirectsToPinUnlock(): void
    {
        $client = static::createClient();
        $this->clearMailpit();

        $email = sprintf('existing-%s@example.com', time());

        $client->jsonRequest('POST', '/api/auth/request-otp', ['email' => $email]);
        self::assertResponseIsSuccessful();
        $firstPayload = $this->fetchSingleMailpitMessage();
        preg_match('/\R([0-9]{3}) ([0-9]{3})\R\RCe code expire/', $firstPayload['Text'], $firstMatch);

        $client->jsonRequest('POST', '/api/auth/verify-otp', [
            'email' => $email,
            'code' => $firstMatch[1].$firstMatch[2],
            'purpose' => 'login',
        ]);
        $verifyPayload = json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);

        $client->jsonRequest('POST', '/api/auth/pin/setup', [
            'pinSetupToken' => $verifyPayload['pinSetupToken'],
            'pin' => '1234',
            'pinConfirmation' => '1234',
        ]);
        self::assertResponseIsSuccessful();

        $this->clearMailpit();
        $client->jsonRequest('POST', '/api/auth/request-otp', ['email' => $email]);
        self::assertResponseIsSuccessful();
        $secondPayload = $this->fetchSingleMailpitMessage();
        preg_match('#https?://[^\r\n]+/connexion/magic/([^\r\n/]+)/([^\r\n]+)#', $secondPayload['Text'], $secondLink);

        $client->request('GET', sprintf('/api/auth/magic/%s/%s', $secondLink[1], trim($secondLink[2])));
        self::assertResponseIsSuccessful();
        $magicPayload = json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);

        self::assertTrue($magicPayload['ok']);
        self::assertTrue($magicPayload['requiresPinUnlock']);
    }
}
