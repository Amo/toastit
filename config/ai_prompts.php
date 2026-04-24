<?php

use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $container): void {
    $container->parameters()->set('app.ai_prompt_files', [
        'inbound_email_rewrite_system' => [
            'version' => 7,
            'system' => 'ai/prompts/inbound_email_rewrite_system/system.v7.twig',
            'user' => 'ai/prompts/inbound_email_rewrite_system/user.v7.twig',
        ],
        'session_summary_system' => [
            'version' => 2,
            'system' => 'ai/prompts/session_summary_system/system.v2.twig',
            'user' => 'ai/prompts/session_summary_system/user.v2.twig',
        ],
        'toast_curation_draft_system' => [
            'version' => 4,
            'system' => 'ai/prompts/toast_curation_draft_system/system.v4.twig',
            'user' => 'ai/prompts/toast_curation_draft_system/user.v4.twig',
        ],
        'toast_draft_refinement_system' => [
            'version' => 6,
            'system' => 'ai/prompts/toast_draft_refinement_system/system.v6.twig',
            'user' => 'ai/prompts/toast_draft_refinement_system/user.v6.twig',
        ],
        'toast_execution_plan_system' => [
            'version' => 5,
            'system' => 'ai/prompts/toast_execution_plan_system/system.v5.twig',
            'user' => 'ai/prompts/toast_execution_plan_system/user.v5.twig',
        ],
        'todo_digest_system' => [
            'version' => 2,
            'system' => 'ai/prompts/todo_digest_system/system.v2.twig',
            'user' => 'ai/prompts/todo_digest_system/user.v2.twig',
        ],
        'workspace_suggestion_system' => [
            'version' => 2,
            'system' => 'ai/prompts/workspace_suggestion_system/system.v2.twig',
            'user' => 'ai/prompts/workspace_suggestion_system/user.v2.twig',
        ],
    ]);
};
