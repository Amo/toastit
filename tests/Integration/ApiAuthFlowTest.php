<?php

namespace App\Tests\Integration;

use App\Entity\ApiRefreshToken;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class ApiAuthFlowTest extends WebTestCase
{
    use MailpitTestTrait;

    public function testOtpApiCanIssuePinSetupThenRefreshWithPin(): void
    {
        $client = static::createClient();
        $this->clearMailpit();
        $email = sprintf('api-auth-%s@example.com', time());

        $client->jsonRequest('POST', '/api/auth/request-otp', [
            'email' => $email,
        ]);
        self::assertResponseIsSuccessful();

        $payload = $this->fetchSingleMailpitMessage();
        preg_match('/\R([A-Z0-9]{6})\R\RCe code expire/', $payload['Text'], $match);
        $code = $match[1];

        $client->jsonRequest('POST', '/api/auth/verify-otp', [
            'email' => $email,
            'code' => $code,
        ]);
        self::assertResponseIsSuccessful();
        $verifyPayload = json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertTrue($verifyPayload['ok']);
        self::assertTrue($verifyPayload['requiresPinSetup']);
        self::assertArrayHasKey('pinSetupToken', $verifyPayload);

        $client->jsonRequest('POST', '/api/auth/pin/setup', [
            'pinSetupToken' => $verifyPayload['pinSetupToken'],
            'pin' => '1234',
            'pinConfirmation' => '1234',
        ]);
        self::assertResponseIsSuccessful();
        $pinPayload = json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertTrue($pinPayload['ok']);
        self::assertArrayHasKey('accessToken', $pinPayload);
        self::assertArrayHasKey('refreshToken', $pinPayload);

        $client->jsonRequest('POST', '/api/auth/refresh', [
            'refreshToken' => $pinPayload['refreshToken'],
            'pin' => '1234',
        ]);
        self::assertResponseIsSuccessful();
        $refreshPayload = json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertTrue($refreshPayload['ok']);
        self::assertArrayHasKey('accessToken', $refreshPayload);
        self::assertSame($pinPayload['refreshToken'], $refreshPayload['refreshToken']);
    }

    public function testRefreshFailsAfterThirtyMinutesOfInactivity(): void
    {
        $client = static::createClient();
        $this->clearMailpit();
        $email = sprintf('api-auth-inactive-%s@example.com', time());

        $client->jsonRequest('POST', '/api/auth/request-otp', [
            'email' => $email,
        ]);
        self::assertResponseIsSuccessful();

        $payload = $this->fetchSingleMailpitMessage();
        preg_match('/\R([A-Z0-9]{6})\R\RCe code expire/', $payload['Text'], $match);
        $code = $match[1];

        $client->jsonRequest('POST', '/api/auth/verify-otp', [
            'email' => $email,
            'code' => $code,
        ]);
        $verifyPayload = json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);

        $client->jsonRequest('POST', '/api/auth/pin/setup', [
            'pinSetupToken' => $verifyPayload['pinSetupToken'],
            'pin' => '1234',
            'pinConfirmation' => '1234',
        ]);
        $pinPayload = json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);

        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $refreshToken = $entityManager->getRepository(ApiRefreshToken::class)->findOneBy([], ['id' => 'DESC']);
        self::assertInstanceOf(ApiRefreshToken::class, $refreshToken);
        $refreshToken->setLastUsedAt(new \DateTimeImmutable('-31 minutes'));
        $entityManager->flush();

        $client->jsonRequest('POST', '/api/auth/refresh', [
            'refreshToken' => $pinPayload['refreshToken'],
            'pin' => '1234',
        ]);
        self::assertResponseStatusCodeSame(401);
        $refreshPayload = json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertFalse($refreshPayload['ok']);
        self::assertSame('refresh_inactive', $refreshPayload['error']);
    }
}
