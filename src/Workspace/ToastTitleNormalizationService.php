<?php

namespace App\Workspace;

use App\Entity\Toast;

final class ToastTitleNormalizationService
{
    /**
     * @return array{title: string, description: ?string, titleWasTruncated: bool}
     */
    public function normalize(string $title, ?string $description): array
    {
        $normalizedTitle = trim($title);
        $normalizedDescription = $this->normalizeNullableText($description);
        $titleMaxLength = Toast::TITLE_MAX_LENGTH;

        if ($this->strlen($normalizedTitle) <= $titleMaxLength) {
            return [
                'title' => $normalizedTitle,
                'description' => $normalizedDescription,
                'titleWasTruncated' => false,
            ];
        }

        $truncatedTitle = $this->substr($normalizedTitle, 0, $titleMaxLength);
        $descriptionWithoutPrefix = $this->stripExistingOriginalTitlePrefix($normalizedDescription);
        $newDescription = sprintf(
            "Original title: %s%s",
            $normalizedTitle,
            null !== $descriptionWithoutPrefix ? "\n\n".$descriptionWithoutPrefix : ''
        );

        return [
            'title' => $truncatedTitle,
            'description' => $newDescription,
            'titleWasTruncated' => true,
        ];
    }

    private function stripExistingOriginalTitlePrefix(?string $description): ?string
    {
        if (null === $description || '' === $description) {
            return null;
        }

        if (!str_starts_with($description, 'Original title: ')) {
            return $description;
        }

        $remaining = preg_replace('/^Original title: .*?(?:\R\R|$)/us', '', $description, 1);

        return $this->normalizeNullableText($remaining);
    }

    private function normalizeNullableText(?string $value): ?string
    {
        $value = null !== $value ? trim($value) : null;

        return '' === $value ? null : $value;
    }

    private function strlen(string $value): int
    {
        return iconv_strlen($value, 'UTF-8') ?: strlen($value);
    }

    private function substr(string $value, int $start, int $length): string
    {
        $slice = iconv_substr($value, $start, $length, 'UTF-8');

        if (false !== $slice) {
            return $slice;
        }

        return substr($value, $start, $length);
    }
}
