<?php

namespace App\Profile;

use App\Entity\User;
use League\Flysystem\FilesystemOperator;
use League\Flysystem\UnableToDeleteFile;
use League\Flysystem\UnableToReadFile;
use League\Flysystem\UnableToWriteFile;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

final class AvatarStorageService
{
    private const ALLOWED_MIME_TYPES = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'image/gif' => 'gif',
    ];

    private const MIN_WIDTH = 64;
    private const MIN_HEIGHT = 64;
    private const MAX_WIDTH = 256;
    private const MAX_HEIGHT = 256;

    public function __construct(
        private readonly FilesystemOperator $filesystem,
        private readonly string $storagePath,
    ) {
    }

    public function store(User $user, UploadedFile $uploadedFile): string
    {
        if (!$uploadedFile->isValid()) {
            throw new BadRequestHttpException($this->uploadErrorMessage($uploadedFile->getError()));
        }

        $mimeType = $this->resolveMimeType($uploadedFile);

        if (!isset(self::ALLOWED_MIME_TYPES[$mimeType])) {
            throw new BadRequestHttpException('Unsupported image type.');
        }

        $imageSize = @getimagesize($uploadedFile->getPathname());

        if (false === $imageSize) {
            throw new BadRequestHttpException('Uploaded image is not readable.');
        }

        [$width, $height] = $imageSize;

        if (
            $width < self::MIN_WIDTH
            || $height < self::MIN_HEIGHT
            || $width > self::MAX_WIDTH
            || $height > self::MAX_HEIGHT
        ) {
            throw new BadRequestHttpException('Avatar must be between 64x64 and 256x256 pixels.');
        }

        $userId = $user->getId();

        if (null === $userId) {
            throw new \LogicException('User must have an identifier before storing an avatar.');
        }

        $path = sprintf(
            'user-%d-%s.%s',
            $userId,
            bin2hex(random_bytes(8)),
            self::ALLOWED_MIME_TYPES[$mimeType]
        );
        $currentPath = $user->getAvatarPath();

        try {
            $temporaryPath = $uploadedFile->getPathname();

            if ('' === $temporaryPath || !is_readable($temporaryPath)) {
                throw new BadRequestHttpException('Uploaded image is not readable.');
            }

            $stream = fopen($temporaryPath, 'rb');

            if (false === $stream) {
                throw new BadRequestHttpException('Unable to read uploaded file.');
            }

            $this->filesystem->writeStream($path, $stream);

            if (is_resource($stream)) {
                fclose($stream);
            }
        } catch (UnableToWriteFile $exception) {
            throw new \RuntimeException('Unable to store avatar image.', 0, $exception);
        }

        if ($this->isStoredPath($currentPath)) {
            $this->delete($currentPath);
        }

        return $path;
    }

    /**
     * @return resource
     */
    public function readStream(string $path)
    {
        try {
            return $this->filesystem->readStream($path);
        } catch (UnableToReadFile $exception) {
            throw new BadRequestHttpException('Avatar image was not found.', $exception);
        }
    }

    public function resolveStoredMimeType(string $path): string
    {
        $fullPath = $this->storagePath.'/'.$path;

        if (!is_file($fullPath)) {
            throw new BadRequestHttpException('Avatar image was not found.');
        }

        return mime_content_type($fullPath) ?: 'application/octet-stream';
    }

    public function delete(?string $path): void
    {
        if (!$this->isStoredPath($path)) {
            return;
        }

        try {
            $this->filesystem->delete($path);
        } catch (UnableToDeleteFile) {
        }
    }

    public function isStoredPath(?string $path): bool
    {
        return null !== $path
            && '' !== $path
            && !str_contains($path, '/')
            && !str_starts_with($path, 'http://')
            && !str_starts_with($path, 'https://');
    }

    private function resolveMimeType(UploadedFile $uploadedFile): string
    {
        $mimeType = $uploadedFile->getClientMimeType() ?: '';

        if (isset(self::ALLOWED_MIME_TYPES[$mimeType])) {
            return $mimeType;
        }

        try {
            return $uploadedFile->getMimeType() ?? '';
        } catch (\Throwable) {
            return '';
        }
    }

    private function uploadErrorMessage(int $error): string
    {
        return match ($error) {
            \UPLOAD_ERR_INI_SIZE, \UPLOAD_ERR_FORM_SIZE => 'Uploaded image is too large.',
            \UPLOAD_ERR_PARTIAL => 'Uploaded image was only partially received.',
            \UPLOAD_ERR_NO_FILE => 'No image was uploaded.',
            default => 'Invalid uploaded image.',
        };
    }
}
