<?php

namespace App\Meeting;

use App\Ai\AiPromptTemplateService;
use App\Entity\ToastingSession;
use App\Entity\User;
use App\Entity\Workspace;

final class ToastingSessionSummaryService
{
    public function __construct(
        private readonly ToastingSessionSummaryBuilder $summaryBuilder,
        private readonly XaiTextService $xaiText,
        private readonly AiPromptTemplateService $promptTemplate,
    ) {
    }

    /**
     * @return array{
     *     sessionId: int,
     *     startedAt: string,
     *     endedAt: string,
     *     isActive: bool,
     *     sourceItemCount: int,
     *     summary: string
     * }
     */
    public function summarizeLatestSession(Workspace $workspace, User $requestedBy): ToastingSession
    {
        $session = $workspace->getToastingSessions()->first();
        if (!$session instanceof ToastingSession) {
            throw new SessionSummaryUnavailableException('no_session', 'No toasting session exists for this workspace.');
        }

        return $this->generateSessionSummary($workspace, $session, $requestedBy);
    }

    public function generateSessionSummary(Workspace $workspace, ToastingSession $session, User $requestedBy): ToastingSession
    {
        $summaryContext = $this->summaryBuilder->buildPrompt($workspace, $session);
        $generatedAt = new \DateTimeImmutable();
        $systemPrompt = $this->promptTemplate->resolveSystemPrompt('session_summary_system', '');

        if ('' === trim($systemPrompt)) {
            throw new SessionSummaryUnavailableException('invalid_session_summary_prompt', 'No session-summary system prompt is configured.');
        }

        $userPrompt = $this->promptTemplate->resolveUserPromptTemplate(
            'session_summary_system',
            "{{ summary_context }}\n\nRequested by: {{ requested_by }}",
            [
                'summary_context' => $summaryContext['prompt'],
                'requested_by' => $requestedBy->getDisplayName(),
            ],
        );

        $rawSummary = $this->xaiText->generateSummary(
            $systemPrompt,
            $userPrompt,
            [
                'source' => 'session_summary',
                'userId' => $requestedBy->getId(),
                'workspaceId' => $workspace->getId(),
                'sessionId' => $session->getId(),
            ],
        );

        $session->setSummary($this->extractSummaryMarkdown($rawSummary), $generatedAt);
        $session
            ->setSummaryGeneratedAt($generatedAt)
            ->setSummaryUpdatedAt($generatedAt);

        return $session;
    }

    public function updateSessionSummary(ToastingSession $session, string $summary): ToastingSession
    {
        $session->setSummary($summary, new \DateTimeImmutable());

        return $session;
    }

    private function extractSummaryMarkdown(string $rawSummary): string
    {
        $payload = json_decode(trim($rawSummary), true);
        if (is_array($payload) && is_array($payload['result'] ?? null) && is_string($payload['result']['markdown'] ?? null)) {
            $markdown = trim($payload['result']['markdown']);

            return '' !== $markdown ? $markdown : trim($rawSummary);
        }

        return trim($rawSummary);
    }
}
