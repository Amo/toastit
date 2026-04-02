<?php

namespace App\Tests\Integration;

use App\Entity\User;
use App\Entity\Workspace;
use App\Security\JwtTokenService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class WorkspaceDeletionFlowTest extends WebTestCase
{
    use MailpitTestTrait;

    public function testOwnerCanSoftDeleteAndRestoreWorkspaceFromProfile(): void
    {
        $client = static::createClient();
        $ownerEmail = sprintf('workspace-delete-%s@example.com', time());
        $memberEmail = sprintf('workspace-delete-member-%s@example.com', time());

        $this->loginWithMagicLink($client, $ownerEmail);
        $client->setServerParameter('HTTP_AUTHORIZATION', 'Bearer '.$this->createAccessTokenForEmail($ownerEmail));

        $client->jsonRequest('POST', '/api/workspaces', ['name' => 'Recoverable workspace']);
        self::assertResponseIsSuccessful();
        $workspaceId = json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR)['workspaceId'];

        $client->jsonRequest('POST', sprintf('/api/workspaces/%d/invite', $workspaceId), ['email' => $memberEmail]);
        self::assertResponseIsSuccessful();

        $client->request('DELETE', sprintf('/api/workspaces/%d', $workspaceId));
        self::assertResponseIsSuccessful();

        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $entityManager->clear();
        $workspace = $entityManager->getRepository(Workspace::class)->find($workspaceId);
        self::assertInstanceOf(Workspace::class, $workspace);
        self::assertTrue($workspace->isDeleted());

        $client->request('GET', '/api/dashboard');
        self::assertResponseIsSuccessful();
        $dashboardPayload = json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertNotContains($workspaceId, array_column($dashboardPayload['workspaces'], 'id'));

        $client->request('GET', sprintf('/api/workspaces/%d', $workspaceId));
        self::assertResponseStatusCodeSame(404);

        $client->request('GET', '/api/profile');
        self::assertResponseIsSuccessful();
        $profilePayload = json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertContains($workspaceId, array_column($profilePayload['deletedWorkspaces'], 'id'));

        $client->request('POST', sprintf('/api/workspaces/%d/restore', $workspaceId));
        self::assertResponseIsSuccessful();

        $entityManager->clear();
        $restoredWorkspace = $entityManager->getRepository(Workspace::class)->find($workspaceId);
        self::assertInstanceOf(Workspace::class, $restoredWorkspace);
        self::assertFalse($restoredWorkspace->isDeleted());

        $client->request('GET', '/api/dashboard');
        self::assertResponseIsSuccessful();
        $restoredDashboardPayload = json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertContains($workspaceId, array_column($restoredDashboardPayload['workspaces'], 'id'));
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
