<?php

namespace App\Tests\Unit;

use App\Ai\AiPromptFileStore;
use App\Ai\AiPromptTemplateService;
use PHPUnit\Framework\TestCase;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

final class AiPromptTemplateServiceTest extends TestCase
{
    public function testResolveSystemPromptUsesFileBackedPromptBeforeDatabase(): void
    {
        $twig = new Environment(new ArrayLoader([
            'ai/prompts/test_prompt/system.v1.twig' => 'File system prompt for {{ name }}.',
            'ai/prompts/test_prompt/user.v1.twig' => 'Unused user prompt',
        ]));

        $service = new AiPromptTemplateService(
            new AiPromptFileStore($twig, [
                'test_prompt' => [
                    'version' => 1,
                    'system' => 'ai/prompts/test_prompt/system.v1.twig',
                    'user' => 'ai/prompts/test_prompt/user.v1.twig',
                ],
            ]),
            $twig,
        );

        self::assertSame(
            'File system prompt for Amaury.',
            $service->resolveSystemPrompt('test_prompt', 'Database fallback', ['name' => 'Amaury']),
        );
    }

    public function testResolveUserPromptTemplateFallsBackToProvidedTemplateWhenNoFilePromptExists(): void
    {
        $twig = new Environment(new ArrayLoader([]));

        $service = new AiPromptTemplateService(
            new AiPromptFileStore($twig, []),
            $twig,
        );

        self::assertSame(
            'Fallback user prompt for Amaury.',
            $service->resolveUserPromptTemplate('db_only_prompt', 'Fallback user prompt for {{ name }}.', ['name' => 'Amaury']),
        );
    }
}
