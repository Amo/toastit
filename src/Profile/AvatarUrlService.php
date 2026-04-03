<?php

namespace App\Profile;

use App\Entity\User;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class AvatarUrlService
{
    public function __construct(
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly AvatarStorageService $avatarStorage,
    ) {
    }

    public function resolve(User $user, int $size = 80): string
    {
        if ($user->isDeleted()) {
            return '';
        }

        $avatarPath = $user->getAvatarPath();

        if ($this->avatarStorage->isStoredPath($avatarPath)) {
            return $this->urlGenerator->generate('profile_avatar_public', ['path' => $avatarPath]);
        }

        return $user->getGravatarUrl($size);
    }
}
