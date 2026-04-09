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

    /**
     * @return array{
     *   title: string,
     *   description: string,
     *   workspace: string,
     *   owner: string,
     *   due_on: ?string,
     *   reason: string,
     *   confidence: int
     * }|null
     */
    public function suggestInboundRewrite(User $user, string $sender, string $emailTitle, ?string $emailBody): ?array
    {
        $workspaces = $this->workspaceRepository->findForUser($user);
        if ([] === $workspaces || !$this->xaiText->isConfigured()) {
            return null;
        }

        $systemPrompt = $this->promptTemplate->resolveSystemPrompt('inbound_email_rewrite_system', '');
        if ('' === trim($systemPrompt)) {
            return null;
        }

        $workspaceContexts = [];
        foreach ($workspaces as $workspace) {
            $participants = [];
            $participantsById = [];

            $organizer = $workspace->getOrganizer();
            if (null !== $organizer->getId()) {
                $participantsById[$organizer->getId()] = $organizer;
            }

            foreach ($workspace->getMemberships() as $membership) {
                $member = $membership->getUser();
                if (null !== $member->getId()) {
                    $participantsById[$member->getId()] = $member;
                }
            }

            foreach (array_values($participantsById) as $participant) {
                $participants[] = sprintf(
                    '- %s <%s>',
                    $participant->getDisplayName(),
                    $participant->getEmail(),
                );
            }

            $workspaceContexts[] = sprintf(
                "Workspace: %s\nDefault due preset: %s\nParticipants:\n%s",
                $workspace->getName(),
                $workspace->getDefaultDuePreset(),
                [] !== $participants ? implode("\n", $participants) : '- none',
            );
        }

        $userPrompt = $this->promptTemplate->resolveUserPromptTemplate(
            'inbound_email_rewrite_system',
            '',
            [
                'sender' => trim($sender),
                'email_title' => trim($emailTitle),
                'email_body' => trim((string) $emailBody) ?: '(No email body provided)',
                'requested_by_display_name' => $user->getDisplayName(),
                'requested_by_email' => $user->getEmail(),
                'reword_language_instruction' => $this->buildRewordLanguageInstruction($user),
                'reference_datetime' => (new \DateTimeImmutable('now'))->format(\DateTimeInterface::ATOM),
                'reference_timezone' => date_default_timezone_get(),
                'workspace_contexts' => implode("\n\n", $workspaceContexts),
            ],
        );
        if ('' === trim($userPrompt)) {
            return null;
        }

        try {
            $response = $this->xaiText->generateText(
                $systemPrompt,
                $userPrompt,
                [
                    'source' => 'inbound_email_rewrite',
                    'userId' => $user->getId(),
                ],
            );
        } catch (SessionSummaryUnavailableException) {
            return null;
        }

        $payload = json_decode(trim($response), true);
        if (!is_array($payload) || !is_array($payload['result'] ?? null)) {
            return null;
        }

        $result = $payload['result'];
        $title = trim((string) ($result['title'] ?? ''));
        $description = trim((string) ($result['description'] ?? ''));
        $workspace = trim((string) ($result['workspace'] ?? ''));
        $owner = trim((string) ($result['owner'] ?? ''));
        $dueOn = trim((string) ($result['due_on'] ?? ''));
        $reason = trim((string) ($result['reason'] ?? ''));
        $confidence = (int) ($result['confidence'] ?? 0);

        if ('' === $title || '' === $description || '' === $workspace || '' === $owner) {
            return null;
        }

        if ('NONE' === strtoupper($dueOn)) {
            $dueOn = null;
        }

        return [
            'title' => $title,
            'description' => $description,
            'workspace' => $workspace,
            'owner' => $owner,
            'due_on' => $dueOn,
            'reason' => $reason,
            'confidence' => max(0, min(100, $confidence)),
        ];
    }

    private function buildRewordLanguageInstruction(User $user): string
    {
        $preferredLanguage = $user->getInboundRewordLanguage();
        if (null === $preferredLanguage || '' === trim($preferredLanguage)) {
            return 'Detect language from the inbound email title/body and write title and description in that same language.';
        }

        return sprintf(
            'Force output language for title and description to: %s.',
            User::getInboundRewordLanguageLabel($preferredLanguage),
        );
    }

}
