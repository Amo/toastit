<?php

namespace App\Meeting;

use App\Entity\ToastingSession;
use App\Entity\User;
use App\Entity\Workspace;

final class ToastingSessionSummaryService
{
    public function __construct(
        private readonly ToastingSessionSummaryBuilder $summaryBuilder,
        private readonly XaiTextService $xaiText,
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
        $session->setSummary($this->xaiText->generateSummary(
            <<<'PROMPT'
You produce operational meeting recaps for Toastit.
Your output must stay grounded in the provided workspace/session data.
Do not invent decisions, owners, dates, or follow-ups.
When information is ambiguous or missing, call it out explicitly.
Keep the recap concise, actionable, and suitable for sharing with the team.
PROMPT,
            $summaryContext['prompt'].sprintf("\n\nRequested by: %s", $requestedBy->getDisplayName())
        ), $generatedAt);
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
}
