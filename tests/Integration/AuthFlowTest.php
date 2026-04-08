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
        $previewPayload = json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertTrue($previewPayload['ok']);
        self::assertTrue($previewPayload['requiresPinUnlock']);

        $client->jsonRequest('POST', sprintf('/api/auth/magic/%s/%s/consume', $secondLink[1], trim($secondLink[2])));
        self::assertResponseIsSuccessful();
        $magicPayload = json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);

        self::assertTrue($magicPayload['ok']);
        self::assertTrue($magicPayload['requiresPinUnlock']);
    }

    public function testFirstPinSetupSendsOnboardingEmail(): void
    {
        $client = static::createClient();
        $this->clearMailpit();
        $email = sprintf('onboarding-%s@example.com', time());

        $client->jsonRequest('POST', '/api/auth/request-otp', ['email' => $email]);
        self::assertResponseIsSuccessful();
        $payload = $this->fetchSingleMailpitMessage();
        preg_match('/\R([0-9]{3}) ([0-9]{3})\R\RCe code expire/', $payload['Text'], $match);

        $client->jsonRequest('POST', '/api/auth/verify-otp', [
            'email' => $email,
            'code' => $match[1].$match[2],
            'purpose' => 'login',
        ]);
        self::assertResponseIsSuccessful();
        $verifyPayload = json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);

        $this->clearMailpit();

        $client->jsonRequest('POST', '/api/auth/pin/setup', [
            'pinSetupToken' => $verifyPayload['pinSetupToken'],
            'pin' => '1234',
            'pinConfirmation' => '1234',
        ]);
        self::assertResponseIsSuccessful();

        $onboardingMail = $this->fetchSingleMailpitMessage();

        self::assertSame('Welcome to Toastit', $onboardingMail['Subject']);
        self::assertStringContainsString('Add this address to your contacts', $onboardingMail['Text']);
        self::assertStringContainsString('subject "todo"', $onboardingMail['Text']);
        self::assertStringContainsString('"reword"', $onboardingMail['Text']);
        self::assertStringContainsString('"transfer"', $onboardingMail['Text']);
        self::assertStringContainsString('hello@toastit.cc', $onboardingMail['Text']);
    }
}
