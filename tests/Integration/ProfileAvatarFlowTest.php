<?php

namespace App\Tests\Integration;

use App\Entity\User;
use App\Security\JwtTokenService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;

final class ProfileAvatarFlowTest extends WebTestCase
{
    use MailpitTestTrait;

    public function testUserCanConfigureInboundAiAutoApplyPreferencesFromProfile(): void
    {
        $client = static::createClient();
        $email = sprintf('profile-ai-prefs-%s@example.com', time());
        $this->loginWithMagicLink($client, $email);
        $client->setServerParameter('HTTP_AUTHORIZATION', 'Bearer '.$this->createAccessTokenForEmail($email));

        $client->request('GET', '/api/profile');
        self::assertResponseIsSuccessful();
        $initialPayload = json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);

        self::assertSame([
            'reword' => true,
            'assignee' => true,
            'dueDate' => true,
            'workspace' => true,
        ], $initialPayload['user']['inboundAiAutoApply']);

        $client->request('PUT', '/api/profile', server: ['CONTENT_TYPE' => 'application/json'], content: json_encode([
            'firstName' => 'Jean',
            'lastName' => 'Dupont',
            'inboundAiAutoApply' => [
                'reword' => false,
                'assignee' => true,
                'dueDate' => false,
                'workspace' => true,
            ],
        ], JSON_THROW_ON_ERROR));
        self::assertResponseIsSuccessful();
        $updatePayload = json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame([
            'reword' => false,
            'assignee' => true,
            'dueDate' => false,
            'workspace' => true,
        ], $updatePayload['user']['inboundAiAutoApply']);

        $client->request('GET', '/api/profile');
        self::assertResponseIsSuccessful();
        $reloadedPayload = json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame([
            'reword' => false,
            'assignee' => true,
            'dueDate' => false,
            'workspace' => true,
        ], $reloadedPayload['user']['inboundAiAutoApply']);
    }

    public function testAuthenticatedUserCanUploadAvatarAndReadItFromReturnedUrl(): void
    {
        $client = static::createClient();
        $email = sprintf('profile-avatar-%s@example.com', time());
        $this->loginWithMagicLink($client, $email);
        $client->setServerParameter('HTTP_AUTHORIZATION', 'Bearer '.$this->createAccessTokenForEmail($email));

        $filePath = tempnam(sys_get_temp_dir(), 'toastit-avatar');
        self::assertNotFalse($filePath);
        file_put_contents($filePath, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAEAAAABACAYAAACqaXHeAAAAlElEQVR4nO3QMREAMBDDsPAn/YWhoR60+7zb7mfTAVoDdIDWAB2gNUAHaA3QAVoDdIDWAB2gNUAHaA3QAVoDdIDWAB2gNUAHaA3QAVoDdIDWAB2gNUAHaA3QAVoDdIDWAB2gNUAHaA3QAVoDdIDWAB2gNUAHaA3QAVoDdIDWAB2gNUAHaA3QAVoDdIDWAB2gNUAHaA9DiOHSbdjxEgAAAABJRU5ErkJggg==', true));
        $uploadedFile = new UploadedFile($filePath, 'avatar.png', 'image/png', test: true);

        $client->request('POST', '/api/profile/avatar', [], [
            'avatar' => $uploadedFile,
        ]);
        self::assertResponseIsSuccessful();

        $payload = json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertTrue($payload['ok']);
        self::assertStringStartsWith('/avatars/user-', $payload['user']['gravatarUrl']);

        $client->setServerParameter('HTTP_AUTHORIZATION', '');
        $client->request('GET', $payload['user']['gravatarUrl']);
        self::assertResponseIsSuccessful();
        self::assertSame('image/png', $client->getResponse()->headers->get('Content-Type'));

        @unlink($filePath);
    }

    public function testAvatarUploadRejectsImagesLargerThanMaximumDimensions(): void
    {
        $client = static::createClient();
        $email = sprintf('profile-avatar-reject-%s@example.com', time());
        $this->loginWithMagicLink($client, $email);
        $client->setServerParameter('HTTP_AUTHORIZATION', 'Bearer '.$this->createAccessTokenForEmail($email));

        $filePath = tempnam(sys_get_temp_dir(), 'toastit-avatar-large');
        self::assertNotFalse($filePath);
        file_put_contents($filePath, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAQEAAAEBCAYAAAB47BD9AAADH0lEQVR4nO3UoQEAMBCEsNt/6XaMF0TEo9jbHtC16wDglglAnAlAnAlAnAlAnAlAnAlAnAlAnAlAnAlAnAlAnAlAnAlAnAlAnAlAnAlAnAlAnAlAnAlAnAlAnAlAnAlAnAlAnAlAnAlAnAlAnAlAnAlAnAlAnAlAnAlAnAlAnAlAnAlAnAlAnAlAnAlAnAlAnAlAnAlAnAlAnAlAnAlAnAlAnAlAnAlAnAlAnAlAnAlAnAlAnAlAnAlAnAlAnAlAnAlAnAlAnAlAnAlAnAlAnAlAnAlAnAlAnAlAnAlAnAlAnAlAnAlAnAlAnAlAnAlAnAlAnAlAnAlAnAlAnAlAnAlAnAlAnAlAnAlAnAlAnAlAnAlAnAlAnAlAnAlAnAlAnAlAnAlAnAlAnAlAnAlAnAlAnAlAnAlAnAlAnAlAnAlAnAlAnAlAnAlAnAlAnAlAnAlAnAlAnAlAnAlAnAlAnAlAnAlAnAlAnAlAnAlAnAlAnAlAnAlAnAlAnAlAnAlAnAlAnAlAnAlAnAlAnAlAnAlAnAlAnAlAnAlAnAlAnAlAnAlAnAlAnAlAnAlAnAlAnAlAnAlAnAlAnAlAnAlAnAlAnAlAnAlAnAlAnAlAnAlAnAlAnAlAnAlAnAlAnAlAnAlAnAlAnAlAnAlAnAlAnAlAnAlAnALAnAlAnAlAnAlAnAlAnAlAnAlAnAlAnAlAnAlAnAlAnAlAnAlAnAlAnAlAnAlAnAlAnAlAnAlAnAlAnAlAnAlAnAlAnAlAnAlAnAlAnAlAnAlAnAlAnAlAnAlAnAlAnAlAnAlAnAlAnAlAnAlAnAlAnAlAnAlAnAlAnAlAnAlAnAlAnAlAnAlAnAlAnAlAnAlAnAlAnAlAnAlAnAlAnAlAnAlAnAlAnAlAnAlAnAlAnAlAnAlAnAlAnALAnAlAnAlAnAlAnAlAnAlAnAlAnAlAnAlAnAlAnAlAnAlAnAlAnAlAnAlAnAlAnAlAnAlAnAlAnAlAnAlAnAlAnAlAnAlAnAlAnAlAnAlAnAlAnAlAnAlAnAlAnAlAnAlAnAlAnAlAnAlAnAlAnAlAnAlAnAlAnAlAnAlAnAlAnAlAnAlAnAlAnAlAnAlAnAlAnAlAnAlAnAlAnAlA3AcgbRwdNcTs2gAAAABJRU5ErkJggg==', true));
        $uploadedFile = new UploadedFile($filePath, 'avatar-large.png', 'image/png', test: true);

        $client->request('POST', '/api/profile/avatar', [], [
            'avatar' => $uploadedFile,
        ]);
        self::assertResponseStatusCodeSame(400);

        $payload = json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertFalse($payload['ok']);
        self::assertSame('Avatar must be between 64x64 and 256x256 pixels.', $payload['message']);

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
