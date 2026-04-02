<?php

namespace App\Tests\Integration;

use App\Entity\Toast;
use App\Entity\ToastComment;
use App\Entity\User;
use App\Entity\Workspace;
use App\Entity\WorkspaceMember;
use App\Security\JwtTokenService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class ProfileDeletionFlowTest extends WebTestCase
{
    use MailpitTestTrait;

    public function testDeletingAccountTransfersWorkspaceOwnershipAndAnonymizesHistoricalContent(): void
    {
        $client = static::createClient();
        $ownerEmail = sprintf('delete-owner-%s@example.com', time());
        $memberEmail = sprintf('delete-member-%s@example.com', time());
        $toastTitle = sprintf('Legacy toast %s', microtime(true));

        $this->loginWithMagicLink($client, $ownerEmail);
        $client->setServerParameter('HTTP_AUTHORIZATION', 'Bearer '.$this->createAccessTokenForEmail($ownerEmail));

        $client->jsonRequest('POST', '/api/workspaces', ['name' => 'Deletion test']);
        self::assertResponseIsSuccessful();
        $workspaceId = $this->decodeJsonResponse($client)['workspaceId'];

        $client->jsonRequest('POST', sprintf('/api/workspaces/%d/invite', $workspaceId), ['email' => $memberEmail]);
        self::assertResponseIsSuccessful();

        $client->jsonRequest('POST', sprintf('/api/workspaces/%d/items', $workspaceId), [
            'title' => $toastTitle,
            'description' => 'Content kept after deletion',
        ]);
        self::assertResponseIsSuccessful();

        $toast = $this->findToastByTitle($toastTitle);
        $comment = (new ToastComment())
            ->setToast($toast)
            ->setAuthor($toast->getAuthor())
            ->setContent('Legacy comment');

        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $entityManager->persist($comment);
        $entityManager->flush();

        $client->request('DELETE', '/api/profile', server: ['CONTENT_TYPE' => 'application/json'], content: json_encode([
            'confirmation' => 'DELETE',
            'otp' => '000000',
        ], JSON_THROW_ON_ERROR));
        self::assertResponseStatusCodeSame(401);

        $this->clearMailpit();
        $client->jsonRequest('POST', '/api/profile/delete-request');
        self::assertResponseIsSuccessful();
        $payload = $this->fetchSingleMailpitMessage();
        preg_match('/\R([0-9]{3}) ([0-9]{3})\R/', $payload['Text'], $match);

        $client->request('DELETE', '/api/profile', server: ['CONTENT_TYPE' => 'application/json'], content: json_encode([
            'confirmation' => 'DELETE',
            'otp' => $match[1].$match[2],
        ], JSON_THROW_ON_ERROR));
        self::assertResponseIsSuccessful();

        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $entityManager->clear();

        $deletedUser = $entityManager->getRepository(User::class)->findOneBy(['id' => $toast->getAuthor()->getId()]);
        self::assertInstanceOf(User::class, $deletedUser);
        self::assertTrue($deletedUser->isDeleted());
        self::assertSame('Deleted user', $deletedUser->getDisplayName());

        $workspace = $entityManager->getRepository(Workspace::class)->find($workspaceId);
        self::assertInstanceOf(Workspace::class, $workspace);
        self::assertSame($memberEmail, $workspace->getOrganizer()->getEmail());
        self::assertSame(1, $workspace->getOwnerCount());

        $remainingMemberships = $entityManager->getRepository(WorkspaceMember::class)->findBy(['workspace' => $workspace]);
        self::assertCount(1, $remainingMemberships);
        self::assertSame($memberEmail, $remainingMemberships[0]->getUser()->getEmail());
        self::assertTrue($remainingMemberships[0]->isOwner());

        $reloadedToast = $entityManager->getRepository(Toast::class)->find($toast->getId());
        self::assertInstanceOf(Toast::class, $reloadedToast);
        self::assertSame('Deleted user', $reloadedToast->getAuthor()->getDisplayName());
        self::assertSame('Deleted user', $reloadedToast->getComments()->first()->getAuthor()->getDisplayName());

        $client->request('GET', '/api/dashboard');
        self::assertResponseStatusCodeSame(401);
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

    private function decodeJsonResponse(KernelBrowser $client): array
    {
        return json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
    }

    private function findToastByTitle(string $title): Toast
    {
        $toast = static::getContainer()->get(EntityManagerInterface::class)
            ->getRepository(Toast::class)
            ->findOneBy(['title' => $title]);

        self::assertInstanceOf(Toast::class, $toast);

        return $toast;
    }
}
