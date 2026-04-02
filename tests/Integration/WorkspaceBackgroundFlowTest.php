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

        $client->request('POST', '/app', ['name' => 'Background']);
        self::assertResponseRedirects();
        preg_match('#/app/workspaces/(\d+)$#', (string) $client->getResponse()->headers->get('Location'), $matches);
        $workspaceId = (int) $matches[1];

        $filePath = tempnam(sys_get_temp_dir(), 'toastit-background');
        self::assertNotFalse($filePath);
        file_put_contents($filePath, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO6pK3sAAAAASUVORK5CYII=', true));
        $uploadedFile = new UploadedFile($filePath, 'background.png', 'image/png', test: true);

        $client->setServerParameter('HTTP_AUTHORIZATION', 'Bearer '.$this->createAccessTokenForEmail($email));
        $client->request('POST', sprintf('/api/workspaces/%d/background', $workspaceId), [], [
            'background' => $uploadedFile,
        ]);
        self::assertResponseIsSuccessful();

        $client->request('GET', sprintf('/api/workspaces/%d/background', $workspaceId));
        self::assertResponseIsSuccessful();
        self::assertSame('image/png', $client->getResponse()->headers->get('Content-Type'));
        self::assertNotEmpty(glob(dirname(__DIR__, 2).'/var/storage/workspace/background/workspace-'.$workspaceId.'.*'));
        $client->setServerParameter('HTTP_AUTHORIZATION', '');

        @unlink($filePath);
    }

    private function loginWithMagicLink(KernelBrowser $client, string $email): void
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
