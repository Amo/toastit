<?php

namespace App\Tests\Integration;

use App\Security\JwtTokenService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class PinFlowTest extends WebTestCase
{
    use MailpitTestTrait;

    public function testOtpAndPinSetupUnlockProtectedApp(): void
    {
        $client = static::createClient();
        $this->clearMailpit();

        $email = sprintf('pin-%s@example.com', time());

        $client->jsonRequest('POST', '/api/auth/request-otp', ['email' => $email]);
        self::assertResponseIsSuccessful();
        $payload = $this->fetchSingleMailpitMessage();
        preg_match('/\R([0-9]{3}) ([0-9]{3})\R\RCe code expire/', $payload['Text'], $match);
        $code = $match[1].$match[2];

        $client->jsonRequest('POST', '/api/auth/verify-otp', [
            'email' => $email,
            'purpose' => 'login',
            'code' => $code,
        ]);
        self::assertResponseIsSuccessful();
        $verifyPayload = json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertTrue($verifyPayload['requiresPinSetup']);

        $client->jsonRequest('POST', '/api/auth/pin/setup', [
            'pinSetupToken' => $verifyPayload['pinSetupToken'],
            'pin' => '1234',
            'pinConfirmation' => '1234',
        ]);
        self::assertResponseIsSuccessful();
        $pinPayload = json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);

        $client->setServerParameter('HTTP_AUTHORIZATION', 'Bearer '.$pinPayload['accessToken']);
        $client->request('GET', '/api/dashboard');
        self::assertResponseIsSuccessful();
        self::assertArrayHasKey('workspaces', json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR));
    }
}
