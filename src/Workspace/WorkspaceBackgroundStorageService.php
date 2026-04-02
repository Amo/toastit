<?php

namespace App\Workspace;

use App\Entity\Workspace;
use League\Flysystem\FilesystemOperator;
use League\Flysystem\UnableToDeleteFile;
use League\Flysystem\UnableToReadFile;
use League\Flysystem\UnableToWriteFile;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

final class WorkspaceBackgroundStorageService
{
    private const ALLOWED_MIME_TYPES = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'image/gif' => 'gif',
    ];

    public function __construct(
        private readonly FilesystemOperator $filesystem,
        private readonly string $storagePath,
    ) {
    }

    public function store(Workspace $workspace, UploadedFile $uploadedFile): string
    {
        if (!$uploadedFile->isValid()) {
            throw new BadRequestHttpException($this->uploadErrorMessage($uploadedFile->getError()));
        }

        $mimeType = $uploadedFile->getClientMimeType() ?: '';

        if (!isset(self::ALLOWED_MIME_TYPES[$mimeType])) {
            try {
                $mimeType = $uploadedFile->getMimeType() ?? '';
            } catch (\Throwable) {
                $mimeType = '';
            }
        }

        if (!isset(self::ALLOWED_MIME_TYPES[$mimeType])) {
            throw new BadRequestHttpException('Unsupported image type.');
        }

        $workspaceId = $workspace->getId();

        if (null === $workspaceId) {
            throw new \LogicException('Workspace must have an identifier before storing a background.');
        }

        $extension = self::ALLOWED_MIME_TYPES[$mimeType];
        $path = sprintf('workspace-%d.%s', $workspaceId, $extension);

        $currentPath = $workspace->getPermalinkBackgroundUrl();

        if ($this->isStoredPath($currentPath) && $currentPath !== $path) {
            $this->delete($currentPath);
        }

        try {
            $path = $uploadedFile->getPathname();

            if ('' === $path || !is_readable($path)) {
                throw new BadRequestHttpException('Uploaded image is not readable.');
            }

            $stream = fopen($path, 'rb');

            if (false === $stream) {
                throw new BadRequestHttpException('Unable to read uploaded file.');
            }

            $this->filesystem->writeStream($path, $stream);

            if (is_resource($stream)) {
                fclose($stream);
            }
        } catch (UnableToWriteFile $exception) {
            throw new \RuntimeException('Unable to store workspace background.', 0, $exception);
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
            throw new BadRequestHttpException('Workspace background was not found.', $exception);
        }
    }

    public function resolveMimeType(string $path): string
    {
        $fullPath = $this->storagePath.'/'.$path;

        if (!is_file($fullPath)) {
            throw new BadRequestHttpException('Workspace background was not found.');
        }

        return mime_content_type($fullPath) ?: 'application/octet-stream';
    }

    public function isStoredPath(?string $path): bool
    {
        return null !== $path
            && '' !== $path
            && !str_starts_with($path, 'http://')
            && !str_starts_with($path, 'https://');
    }

    private function delete(string $path): void
    {
        try {
            $this->filesystem->delete($path);
        } catch (UnableToDeleteFile) {
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
