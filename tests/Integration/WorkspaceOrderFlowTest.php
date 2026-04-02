<?php

namespace App\Tests\Integration;

use App\Entity\User;
use App\Entity\WorkspaceMember;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class WorkspaceOrderFlowTest extends WebTestCase
{
    use MailpitTestTrait;

    public function testAuthenticatedUserCanPersistWorkspaceOrder(): void
    {
        $client = static::createClient();
        $email = sprintf('order-%s@example.com', time());
        $this->loginWithMagicLink($client, $email);
        $client->setServerParameter('HTTP_AUTHORIZATION', 'Bearer '.$this->createAccessTokenForEmail($email));

        $firstWorkspaceId = $this->createWorkspaceAndReturnId($client, 'Alpha');
        $secondWorkspaceId = $this->createWorkspaceAndReturnId($client, 'Bravo');
        $thirdWorkspaceId = $this->createWorkspaceAndReturnId($client, 'Charlie');

        $client->request('GET', '/api/dashboard');
        self::assertResponseIsSuccessful();
        $payload = json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $defaultWorkspaceId = $payload['workspaces'][0]['id'];

        self::assertSame([$defaultWorkspaceId, $firstWorkspaceId, $secondWorkspaceId, $thirdWorkspaceId], array_column($payload['workspaces'], 'id'));

        $client->request('POST', '/api/workspaces/reorder', server: [
            'CONTENT_TYPE' => 'application/json',
        ], content: json_encode([
            'workspaceIds' => [$firstWorkspaceId, $thirdWorkspaceId, $secondWorkspaceId, $defaultWorkspaceId],
        ], JSON_THROW_ON_ERROR));
        self::assertResponseIsSuccessful();

        $client->request('GET', '/api/dashboard');
        self::assertResponseIsSuccessful();
        $reorderedPayload = json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $client->setServerParameter('HTTP_AUTHORIZATION', '');

        self::assertSame([$firstWorkspaceId, $thirdWorkspaceId, $secondWorkspaceId, $defaultWorkspaceId], array_column($reorderedPayload['workspaces'], 'id'));

        $memberships = static::getContainer()->get(EntityManagerInterface::class)
            ->getRepository(WorkspaceMember::class)
            ->findBy(['user' => $this->findUserByEmail($email)], ['displayOrder' => 'ASC']);

        self::assertSame([$firstWorkspaceId, $thirdWorkspaceId, $secondWorkspaceId, $defaultWorkspaceId], array_map(
            static fn (WorkspaceMember $membership): int => $membership->getWorkspace()->getId(),
            $memberships
        ));
    }

    private function loginWithMagicLink(\Symfony\Bundle\FrameworkBundle\KernelBrowser $client, string $email): void
    {
        $this->clearMailpit();

        $client->request('POST', '/connexion', ['email' => $email]);
        $payload = $this->fetchSingleMailpitMessage();
        preg_match('#https?://[^\r\n]+/connexion/magic/[^\r\n]+#', $payload['Text'], $magicLink);
        $client->request('GET', trim($magicLink[0]));
        $client->request('POST', '/pin/setup', [
            'pin' => '1234',
            'pin_confirmation' => '1234',
        ]);
    }

    private function createWorkspaceAndReturnId(\Symfony\Bundle\FrameworkBundle\KernelBrowser $client, string $name): int
    {
        $client->jsonRequest('POST', '/api/workspaces', ['name' => $name]);
        self::assertResponseIsSuccessful();
        $payload = json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);

        return (int) $payload['workspaceId'];
    }

    private function createAccessTokenForEmail(string $email): string
    {
        $user = $this->findUserByEmail($email);

        return static::getContainer()->get(\App\Security\JwtTokenService::class)
            ->createAccessToken($user, new \DateTimeImmutable());
    }

    private function findUserByEmail(string $email): User
    {
        $user = static::getContainer()->get(EntityManagerInterface::class)
            ->getRepository(User::class)
            ->findOneBy(['email' => $email]);

        self::assertInstanceOf(User::class, $user);

        return $user;
    }
}
