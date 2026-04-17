<?php

namespace App\Ai;

use Twig\Environment;
use Twig\Error\Error;
use Twig\Loader\LoaderInterface;

final class AiPromptFileStore
{
    /**
     * @param array<string, array{version: int, system: string, user: string}> $promptFiles
     */
    public function __construct(
        private readonly Environment $twig,
        private readonly array $promptFiles = [],
    ) {
    }

    public function hasPrompt(string $code): bool
    {
        return null !== $this->getDefinition($code);
    }

    /**
     * @return array{version: int, system: string, user: string}|null
     */
    public function getDefinition(string $code): ?array
    {
        $definition = $this->promptFiles[trim($code)] ?? null;

        if (!is_array($definition)) {
            return null;
        }

        $version = (int) ($definition['version'] ?? 0);
        $system = trim((string) ($definition['system'] ?? ''));
        $user = trim((string) ($definition['user'] ?? ''));

        if ($version < 1 || '' === $system || '' === $user) {
            return null;
        }

        return [
            'version' => $version,
            'system' => $system,
            'user' => $user,
        ];
    }

    /**
     * @param array<string, mixed> $variables
     */
    public function resolveSystemPrompt(string $code, array $variables = []): ?string
    {
        $definition = $this->getDefinition($code);

        return null !== $definition
            ? $this->renderTemplate($definition['system'], $variables)
            : null;
    }

    /**
     * @param array<string, mixed> $variables
     */
    public function resolveUserPromptTemplate(string $code, array $variables = []): ?string
    {
        $definition = $this->getDefinition($code);

        return null !== $definition
            ? $this->renderTemplate($definition['user'], $variables)
            : null;
    }

    public function getSystemPromptSource(string $code): ?string
    {
        $definition = $this->getDefinition($code);

        return null !== $definition
            ? $this->getTemplateSource($definition['system'])
            : null;
    }

    public function getUserPromptTemplateSource(string $code): ?string
    {
        $definition = $this->getDefinition($code);

        return null !== $definition
            ? $this->getTemplateSource($definition['user'])
            : null;
    }

    /**
     * @param array<string, mixed> $variables
     */
    private function renderTemplate(string $path, array $variables): ?string
    {
        $templateSource = $this->getTemplateSource($path);

        if (null === $templateSource) {
            return null;
        }

        try {
            $rendered = trim($this->twig->render($path, $variables));

            return '' !== $rendered ? $rendered : $templateSource;
        } catch (Error) {
            return $templateSource;
        }
    }

    private function getTemplateSource(string $path): ?string
    {
        $loader = $this->twig->getLoader();

        if (!$loader instanceof LoaderInterface || !$loader->exists($path)) {
            return null;
        }

        $source = trim($loader->getSourceContext($path)->getCode());

        return '' !== $source ? $source : null;
    }
}
