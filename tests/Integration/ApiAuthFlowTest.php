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
        preg_match('/\R([0-9]{3}) ([0-9]{3})\R\RCe code expire/', $payload['Text'], $match);
        $code = $match[1].$match[2];

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

    public function testRefreshStillWorksAfterThirtyMinutesOfInactivity(): void
    {
        $client = static::createClient();
        $this->clearMailpit();
        $email = sprintf('api-auth-inactive-%s@example.com', time());

        $client->jsonRequest('POST', '/api/auth/request-otp', [
            'email' => $email,
        ]);
        self::assertResponseIsSuccessful();

        $payload = $this->fetchSingleMailpitMessage();
        preg_match('/\R([0-9]{3}) ([0-9]{3})\R\RCe code expire/', $payload['Text'], $match);
        $code = $match[1].$match[2];

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
        self::assertResponseIsSuccessful();
        $refreshPayload = json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertTrue($refreshPayload['ok']);
        self::assertArrayHasKey('accessToken', $refreshPayload);
        self::assertSame($pinPayload['refreshToken'], $refreshPayload['refreshToken']);
    }

    public function testMagicLinkUnlockUsesPinUnlockTokenEvenIfARefreshTokenExists(): void
    {
        $client = static::createClient();
        $this->clearMailpit();

        $staleEmail = sprintf('api-auth-stale-%s@example.com', time());
        $freshEmail = sprintf('api-auth-fresh-%s@example.com', time());

        $client->jsonRequest('POST', '/api/auth/request-otp', [
            'email' => $staleEmail,
        ]);
        self::assertResponseIsSuccessful();

        $staleMail = $this->fetchSingleMailpitMessage();
        preg_match('/\R([0-9]{3}) ([0-9]{3})\R\RCe code expire/', $staleMail['Text'], $staleMatch);
        $staleCode = $staleMatch[1].$staleMatch[2];

        $client->jsonRequest('POST', '/api/auth/verify-otp', [
            'email' => $staleEmail,
            'code' => $staleCode,
        ]);
        $staleVerifyPayload = json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);

        $client->jsonRequest('POST', '/api/auth/pin/setup', [
            'pinSetupToken' => $staleVerifyPayload['pinSetupToken'],
            'pin' => '1111',
            'pinConfirmation' => '1111',
        ]);
        $stalePinPayload = json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);

        $this->clearMailpit();

        $client->jsonRequest('POST', '/api/auth/request-otp', [
            'email' => $freshEmail,
        ]);
        self::assertResponseIsSuccessful();

        $freshMail = $this->fetchSingleMailpitMessage();
        preg_match('#/connexion/magic/([^/]+)/([^\\s]+)#', $freshMail['Text'], $linkMatch);
        self::assertCount(3, $linkMatch);

        $client->request('GET', sprintf('/api/auth/magic/%s/%s', $linkMatch[1], $linkMatch[2]));
        self::assertResponseIsSuccessful();
        $previewPayload = json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertTrue($previewPayload['ok']);
        self::assertTrue($previewPayload['requiresPinSetup']);

        $client->jsonRequest('POST', sprintf('/api/auth/magic/%s/%s/consume', $linkMatch[1], $linkMatch[2]));
        self::assertResponseIsSuccessful();
        $magicPayload = json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertTrue($magicPayload['ok']);
        self::assertTrue($magicPayload['requiresPinSetup']);

        $client->jsonRequest('POST', '/api/auth/pin/setup', [
            'pinSetupToken' => $magicPayload['pinSetupToken'],
            'pin' => '2222',
            'pinConfirmation' => '2222',
        ]);
        self::assertResponseIsSuccessful();

        $this->clearMailpit();

        $client->jsonRequest('POST', '/api/auth/request-otp', [
            'email' => $freshEmail,
        ]);
        self::assertResponseIsSuccessful();

        $loginMail = $this->fetchSingleMailpitMessage();
        preg_match('#/connexion/magic/([^/]+)/([^\\s]+)#', $loginMail['Text'], $loginLinkMatch);
        self::assertCount(3, $loginLinkMatch);

        $client->request('GET', sprintf('/api/auth/magic/%s/%s', $loginLinkMatch[1], $loginLinkMatch[2]));
        self::assertResponseIsSuccessful();
        $previewLoginPayload = json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertTrue($previewLoginPayload['ok']);
        self::assertTrue($previewLoginPayload['requiresPinUnlock']);

        $client->jsonRequest('POST', sprintf('/api/auth/magic/%s/%s/consume', $loginLinkMatch[1], $loginLinkMatch[2]));
        self::assertResponseIsSuccessful();
        $loginMagicPayload = json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertTrue($loginMagicPayload['ok']);
        self::assertTrue($loginMagicPayload['requiresPinUnlock']);

        $client->jsonRequest('POST', '/api/auth/pin/unlock', [
            'pin' => '2222',
            'refreshToken' => $stalePinPayload['refreshToken'],
            'pinUnlockToken' => $loginMagicPayload['pinUnlockToken'],
        ]);
        self::assertResponseIsSuccessful();
        $unlockPayload = json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertTrue($unlockPayload['ok']);
        self::assertSame($freshEmail, $unlockPayload['user']['email']);
    }
}
