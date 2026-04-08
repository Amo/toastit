<?php

namespace App\Tests\Integration;

use App\Entity\Toast;
use App\Entity\User;
use App\Security\JwtTokenService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class MyActionsFlowTest extends WebTestCase
{
    use MailpitTestTrait;

    public function testAuthenticatedUserCanFetchAssignedActionsAcrossWorkspaces(): void
    {
        $client = static::createClient();
        $ownerEmail = sprintf('actions-owner-%s@example.com', time());
        $memberEmail = sprintf('actions-member-%s@example.com', time());
        $this->loginWithMagicLink($client, $ownerEmail);
        $client->setServerParameter('HTTP_AUTHORIZATION', 'Bearer '.$this->createAccessTokenForEmail($ownerEmail));

        $client->jsonRequest('POST', '/api/workspaces', ['name' => 'Delivery']);
        self::assertResponseIsSuccessful();
        $workspaceId = $this->decodeJsonResponse($client)['workspaceId'];

        $client->jsonRequest('POST', sprintf('/api/workspaces/%d/invite', $workspaceId), ['email' => $memberEmail]);
        self::assertResponseIsSuccessful();

        $memberUserId = $this->findUserIdByEmail($memberEmail);
        $lateTitle = sprintf('Late task %s', microtime(true));
        $soonTitle = sprintf('Soon task %s', microtime(true));

        $client->jsonRequest('POST', sprintf('/api/workspaces/%d/items', $workspaceId), [
            'title' => $lateTitle,
            'description' => 'Needs a concrete owner.',
        ]);
        self::assertResponseIsSuccessful();
        $lateToastId = $this->findToastIdByTitle($lateTitle);
        $client->jsonRequest('PUT', sprintf('/api/items/%d', $lateToastId), [
            'title' => $lateTitle,
            'description' => 'Needs a concrete owner.',
            'ownerId' => $memberUserId,
            'dueOn' => '2000-01-01',
        ]);
        self::assertResponseIsSuccessful();

        $client->jsonRequest('POST', sprintf('/api/workspaces/%d/items', $workspaceId), [
            'title' => $soonTitle,
            'description' => 'Schedule the follow-up.',
        ]);
        self::assertResponseIsSuccessful();
        $soonToastId = $this->findToastIdByTitle($soonTitle);
        $client->jsonRequest('PUT', sprintf('/api/items/%d', $soonToastId), [
            'title' => $soonTitle,
            'description' => 'Schedule the follow-up.',
            'ownerId' => $memberUserId,
            'dueOn' => (new \DateTimeImmutable('today +2 days'))->format('Y-m-d'),
        ]);
        self::assertResponseIsSuccessful();

        static::ensureKernelShutdown();
        $memberClient = static::createClient();
        $this->loginWithMagicLink($memberClient, $memberEmail);
        $memberClient->setServerParameter('HTTP_AUTHORIZATION', 'Bearer '.$this->createAccessTokenForEmail($memberEmail));

        $memberClient->request('GET', '/api/actions');
        self::assertResponseIsSuccessful();
        $payload = json_decode((string) $memberClient->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);

        self::assertSame($memberEmail, $payload['currentUser']['email']);
        self::assertSame(2, $payload['summary']['assignedCount']);
        self::assertSame(1, $payload['summary']['lateCount']);
        self::assertSame('Delivery', $payload['actions'][0]['workspace']['name']);
        self::assertSame($lateTitle, $payload['actions'][0]['title']);
        self::assertTrue($payload['actions'][0]['isLate']);
        self::assertSame($soonTitle, $payload['actions'][1]['title']);
    }

    private function loginWithMagicLink(KernelBrowser $client, string $email): void
    {
        $this->clearMailpit();

        $client->jsonRequest('POST', '/api/auth/request-otp', ['email' => $email]);
        self::assertResponseIsSuccessful();
        $payload = $this->fetchSingleMailpitMessage();
        preg_match('/\R([0-9]{3}) ([0-9]{3})\R\RCe code expire/', $payload['Text'], $match);
        $client->jsonRequest('POST', '/api/auth/verify-otp', [
            'email' => $email,
            'purpose' => 'login',
            'code' => $match[1].$match[2],
        ]);
        self::assertResponseIsSuccessful();
        $verifyPayload = json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $client->jsonRequest('POST', '/api/auth/pin/setup', [
            'pinSetupToken' => $verifyPayload['pinSetupToken'],
            'pin' => '1234',
            'pinConfirmation' => '1234',
        ]);
        self::assertResponseIsSuccessful();
    }

    private function findUserIdByEmail(string $email): int
    {
        $user = static::getContainer()->get(EntityManagerInterface::class)
            ->getRepository(User::class)
            ->findOneBy(['email' => $email]);

        self::assertInstanceOf(User::class, $user);

        return $user->getId();
    }

    private function findToastIdByTitle(string $title): int
    {
        $toast = static::getContainer()->get(EntityManagerInterface::class)
            ->getRepository(Toast::class)
            ->findOneBy(['title' => $title]);

        self::assertInstanceOf(Toast::class, $toast);

        return $toast->getId();
    }

    private function decodeJsonResponse(KernelBrowser $client): array
    {
        return json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
    }

    private function createAccessTokenForEmail(string $email): string
    {
        $user = static::getContainer()->get(EntityManagerInterface::class)
            ->getRepository(User::class)
            ->findOneBy(['email' => $email]);

        self::assertInstanceOf(User::class, $user);

        return static::getContainer()->get(JwtTokenService::class)
            ->createAccessToken($user, new \DateTimeImmutable());
    }
}
