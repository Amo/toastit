<?php

namespace App\Controller\Profile;

use App\Profile\AvatarStorageService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Attribute\Route;

final class GetAvatarController extends AbstractController
{
    public function __construct(
        private readonly AvatarStorageService $avatarStorage,
    ) {
    }

    #[Route('/avatars/{path}', name: 'profile_avatar_public', methods: ['GET'], requirements: ['path' => '^[A-Za-z0-9][A-Za-z0-9._-]*$'])]
    public function __invoke(string $path): StreamedResponse
    {
        if (!$this->avatarStorage->isStoredPath($path)) {
            throw $this->createNotFoundException();
        }

        $stream = $this->avatarStorage->readStream($path);
        $mimeType = $this->avatarStorage->resolveStoredMimeType($path);

        return new StreamedResponse(static function () use ($stream): void {
            fpassthru($stream);

            if (is_resource($stream)) {
                fclose($stream);
            }
        }, headers: [
            'Content-Type' => $mimeType,
            'Cache-Control' => 'public, max-age=31536000, immutable',
        ]);
    }
}
