<?php

namespace App\Workspace;

use App\Ai\AiPromptTemplateService;
use App\Entity\User;
use App\Entity\Workspace;
use App\Repository\WorkspaceRepository;
use App\Meeting\XaiTextService;
use App\Meeting\SessionSummaryUnavailableException;

final class WorkspaceSuggestionService
{
    public function __construct(
        private readonly WorkspaceRepository $workspaceRepository,
        private readonly XaiTextService $xaiText,
        private readonly AiPromptTemplateService $promptTemplate,
    ) {
    }

    /**
     * @return array{id: int, name: string, reason: string, confidence: int}|null
     */
    public function suggestWorkspace(User $user, string $title, ?string $description): ?array
    {
        $workspaces = array_values(array_filter(
            $this->workspaceRepository->findForUser($user),
            static fn (Workspace $workspace): bool => !$workspace->isInboxWorkspace(),
        ));

        if ([] === $workspaces || !$this->xaiText->isConfigured()) {
            return null;
        }

        try {
            $systemPrompt = $this->promptTemplate->resolveSystemPrompt('workspace_suggestion_system', '');
            if ('' === trim($systemPrompt)) {
                return null;
            }

            $workspaceListText = implode("\n", array_map(
                static fn (Workspace $workspace): string => sprintf('- %s', $workspace->getName()),
                $workspaces,
            ));

            $userPrompt = $this->promptTemplate->resolveUserPromptTemplate(
                'workspace_suggestion_system',
                "Toast title: {{ toast_title }}\nToast description: {{ toast_description }}\nAvailable workspaces:\n{{ workspace_list_text }}",
                [
                    'toast_title' => trim($title),
                    'toast_description' => trim((string) $description) ?: '(empty)',
                    'workspace_list_text' => $workspaceListText,
                ],
            );

            $response = $this->xaiText->generateText(
                $systemPrompt,
                $userPrompt,
                [
                    'source' => 'workspace_suggestion',
                    'userId' => $user->getId(),
                ],
            );
        } catch (SessionSummaryUnavailableException) {
            return null;
        }

        $normalized = trim(str_replace("\r\n", "\n", $response));
        $payload = json_decode($normalized, true);

        if (is_array($payload) && is_array($payload['result'] ?? null)) {
            $result = $payload['result'];
            $name = trim((string) ($result['workspace'] ?? ''));
            $confidence = (int) ($result['confidence'] ?? 0);
            $reason = trim((string) ($result['reason'] ?? ''));
        } elseif (preg_match('/^WORKSPACE:\s*(.+?)\nCONFIDENCE:\s*(.+?)\nREASON:\s*(.+)$/s', $normalized, $matches)) {
            $name = trim($matches[1]);
            $confidence = (int) trim($matches[2]);
            $reason = trim($matches[3]);
        } else {
            return null;
        }

        if ('' === $name || 'NONE' === strtoupper($name) || $confidence < 90) {
            return null;
        }

        foreach ($workspaces as $workspace) {
            if (0 === strcasecmp($workspace->getName(), $name)) {
                return [
                    'id' => (int) $workspace->getId(),
                    'name' => $workspace->getName(),
                    'reason' => $reason,
                    'confidence' => max(0, min(100, $confidence)),
                ];
            }
        }

        return null;
    }

}
