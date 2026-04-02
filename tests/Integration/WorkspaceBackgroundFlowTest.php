<?php

namespace App\Tests\Integration;

use App\Entity\User;
use App\Security\JwtTokenService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;

final class WorkspaceBackgroundFlowTest extends WebTestCase
{
    use MailpitTestTrait;

    public function testOwnerCanUploadAndStreamWorkspaceBackgroundViaApi(): void
    {
        $client = static::createClient();
        $email = sprintf('workspace-background-%s@example.com', time());
        $this->loginWithMagicLink($client, $email);
        $client->setServerParameter('HTTP_AUTHORIZATION', 'Bearer '.$this->createAccessTokenForEmail($email));

        $client->jsonRequest('POST', '/api/workspaces', ['name' => 'Background']);
        self::assertResponseIsSuccessful();
        $workspaceId = json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR)['workspaceId'];

        $filePath = tempnam(sys_get_temp_dir(), 'toastit-background');
        self::assertNotFalse($filePath);
        file_put_contents($filePath, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO6pK3sAAAAASUVORK5CYII=', true));
        $uploadedFile = new UploadedFile($filePath, 'background.png', 'image/png', test: true);

        $client->request('POST', sprintf('/api/workspaces/%d/background', $workspaceId), [], [
            'background' => $uploadedFile,
        ]);
        self::assertResponseIsSuccessful();

        $client->request('GET', sprintf('/api/workspaces/%d/background', $workspaceId));
        self::assertResponseIsSuccessful();
        self::assertSame('image/png', $client->getResponse()->headers->get('Content-Type'));
        $client->setServerParameter('HTTP_AUTHORIZATION', '');

        @unlink($filePath);
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
