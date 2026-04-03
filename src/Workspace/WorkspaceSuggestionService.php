<?php

namespace App\Workspace;

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
    ) {
    }

    /**
     * @return array{id: int, name: string, reason: string}|null
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
            $response = $this->xaiText->generateText(
                $this->buildSystemPrompt(),
                $this->buildUserPrompt($title, $description, $workspaces),
            );
        } catch (SessionSummaryUnavailableException) {
            return null;
        }

        if (!preg_match('/^WORKSPACE:\s*(.+?)\nREASON:\s*(.+)$/s', trim(str_replace("\r\n", "\n", $response)), $matches)) {
            return null;
        }

        $name = trim($matches[1]);
        $reason = trim($matches[2]);

        if ('' === $name || 'NONE' === strtoupper($name)) {
            return null;
        }

        foreach ($workspaces as $workspace) {
            if (0 === strcasecmp($workspace->getName(), $name)) {
                return [
                    'id' => (int) $workspace->getId(),
                    'name' => $workspace->getName(),
                    'reason' => $reason,
                ];
            }
        }

        return null;
    }

    private function buildSystemPrompt(): string
    {
        return implode("\n", [
            'You choose the best Toastit workspace for a newly created toast.',
            'Return one existing workspace name from the list or NONE.',
            'Prefer the workspace that best matches the topic, ownership, and likely team context.',
            'Output must follow this exact format:',
            'WORKSPACE: <exact workspace name or NONE>',
            'REASON: <single concise sentence>',
        ]);
    }

    /**
     * @param list<Workspace> $workspaces
     */
    private function buildUserPrompt(string $title, ?string $description, array $workspaces): string
    {
        $lines = [
            sprintf('Toast title: %s', trim($title)),
            sprintf('Toast description: %s', trim((string) $description) ?: '(empty)'),
            'Available workspaces:',
        ];

        foreach ($workspaces as $workspace) {
            $lines[] = sprintf('- %s', $workspace->getName());
        }

        return implode("\n", $lines);
    }
}
