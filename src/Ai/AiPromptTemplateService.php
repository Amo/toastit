<?php

namespace App\Ai;

use Twig\Environment;
use Twig\Error\Error;

class AiPromptTemplateService
{
    public function __construct(
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

        return $this->renderTemplate($fallbackPrompt, $variables, $fallbackPrompt);
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

        return $this->renderTemplate($fallbackTemplate, $variables, $fallbackTemplate);
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
}
