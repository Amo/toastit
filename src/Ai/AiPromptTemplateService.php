<?php

namespace App\Ai;

use App\Repository\AiPromptRepository;
use App\Repository\AiPromptVersionRepository;
use Twig\Environment;
use Twig\Error\Error;

class AiPromptTemplateService
{
    public function __construct(
        private readonly AiPromptRepository $promptRepository,
        private readonly AiPromptVersionRepository $promptVersionRepository,
        private readonly AiPromptFileStore $promptFileStore,
        private readonly Environment $twig,
    ) {
    }

    /**
     * @param array<string, mixed> $variables
     */
    public function resolveSystemPrompt(string $code, string $fallbackPrompt, array $variables = []): string
    {
        $filePrompt = $this->promptFileStore->resolveSystemPrompt($code, $variables);
        if (null !== $filePrompt) {
            return $filePrompt;
        }

        $latestVersion = $this->resolveLatestVersion($code);
        $templateSource = $latestVersion?->getSystemPrompt() ?: $fallbackPrompt;

        return $this->renderTemplate($templateSource, $variables, $fallbackPrompt);
    }

    /**
     * @param array<string, mixed> $variables
     */
    public function resolveUserPromptTemplate(string $code, string $fallbackTemplate, array $variables = []): string
    {
        $filePrompt = $this->promptFileStore->resolveUserPromptTemplate($code, $variables);
        if (null !== $filePrompt) {
            return $filePrompt;
        }

        $latestVersion = $this->resolveLatestVersion($code);
        $templateSource = $latestVersion?->getUserPromptTemplate() ?: $fallbackTemplate;

        return $this->renderTemplate($templateSource, $variables, $fallbackTemplate);
    }

    /**
     * @param array<string, mixed> $variables
     */
    private function renderTemplate(string $templateSource, array $variables, string $fallbackPrompt): string
    {
        $templateSource = trim($templateSource);
        $fallbackPrompt = trim($fallbackPrompt);

        try {
            if ('' === $templateSource) {
                return $fallbackPrompt;
            }

            $rendered = $this->twig->createTemplate($templateSource)->render($variables);
            $rendered = trim($rendered);

            return '' !== $rendered ? $rendered : $templateSource;
        } catch (Error) {
            return '' !== $templateSource ? $templateSource : $fallbackPrompt;
        }
    }

    private function resolveLatestVersion(string $code): ?\App\Entity\AiPromptVersion
    {
        $prompt = $this->promptRepository->findOneByCode($code);

        if (null === $prompt) {
            return null;
        }

        return $this->promptVersionRepository->findLatestForPrompt($prompt);
    }
}
