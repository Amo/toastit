<?php

namespace App\Controller\Api\Profile;

use App\Api\ProfilePayloadBuilder;
use App\Profile\AvatarStorageService;
use App\Workspace\WorkspaceAccessService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Attribute\Route;

final class UploadAvatarController extends AbstractController
{
    public function __construct(
        private readonly WorkspaceAccessService $workspaceAccess,
        private readonly AvatarStorageService $avatarStorage,
        private readonly ProfilePayloadBuilder $profilePayloadBuilder,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    #[Route('/api/profile/avatar', name: 'api_profile_avatar_upload', methods: ['POST'])]
    public function __invoke(Request $request): JsonResponse
    {
        $user = $this->workspaceAccess->getUserOrFail();
        $uploadedFile = $request->files->get('avatar');

        if (!$uploadedFile instanceof \Symfony\Component\HttpFoundation\File\UploadedFile) {
            return $this->json([
                'ok' => false,
                'error' => 'missing_avatar',
                'message' => 'No image was uploaded.',
            ], Response::HTTP_BAD_REQUEST);
        }

        try {
            $user->setAvatarPath($this->avatarStorage->store($user, $uploadedFile));
            $this->entityManager->flush();
        } catch (BadRequestHttpException $exception) {
            return $this->json([
                'ok' => false,
                'error' => 'invalid_avatar',
                'message' => $exception->getMessage(),
            ], Response::HTTP_BAD_REQUEST);
        }

        return $this->json([
            'ok' => true,
            'user' => $this->profilePayloadBuilder->buildUser($user),
        ]);
    }
}
