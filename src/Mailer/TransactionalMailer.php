<?php

namespace App\Mailer;

use App\Entity\LoginChallenge;
use App\Entity\Toast;
use App\Entity\ToastingSession;
use App\Entity\User;
use App\Entity\Workspace;
use League\CommonMark\CommonMarkConverter;
use Twig\Environment;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

final class TransactionalMailer
{
    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly Environment $twig,
        private readonly CommonMarkConverter $markdownConverter,
        private readonly string $defaultFrom,
    ) {
    }

    public function sendLoginChallenge(User $user, LoginChallenge $challenge, string $magicLink): void
    {
        $context = [
            'user' => $user,
            'challenge' => $challenge,
            'magic_link' => $magicLink,
        ];

        $email = (new Email())
            ->from(new Address($this->defaultFrom, 'Toastit'))
            ->to($user->getEmail())
            ->subject('Votre code de connexion Toastit')
            ->html($this->twig->render('emails/auth/login_challenge.html.twig', $context))
            ->text($this->twig->render('emails/auth/login_challenge.txt.twig', $context));

        $this->mailer->send($email);
    }

    public function sendDeleteAccountChallenge(User $user, LoginChallenge $challenge): void
    {
        $context = [
            'user' => $user,
            'challenge' => $challenge,
        ];

        $email = (new Email())
            ->from(new Address($this->defaultFrom, 'Toastit'))
            ->to($user->getEmail())
            ->subject('Votre code de suppression de compte Toastit')
            ->html($this->twig->render('emails/auth/delete_account_challenge.html.twig', $context))
            ->text($this->twig->render('emails/auth/delete_account_challenge.txt.twig', $context));

        $this->mailer->send($email);
    }

    public function sendOnboarding(User $user, string $inboxEmailAddress): void
    {
        $context = [
            'user' => $user,
            'inbox_email_address' => $inboxEmailAddress,
            'contact_email' => 'hello@toastit.cc',
        ];

        $email = (new Email())
            ->from(new Address($this->defaultFrom, 'Toastit'))
            ->to($user->getEmail())
            ->subject('Welcome to Toastit')
            ->html($this->twig->render('emails/onboarding.html.twig', $context))
            ->text($this->twig->render('emails/onboarding.txt.twig', $context));

        $this->mailer->send($email);
    }

    public function sendTodoDigest(
        User $user,
        string $summary,
        ?string $originalSubject = null,
        ?string $messageId = null,
        ?string $references = null,
        ?string $replyToAddress = null,
    ): void
    {
        $summary = trim($summary);
        if ('' === $summary) {
            return;
        }

        $context = [
            'user' => $user,
            'summary_html' => $this->markdownConverter->convert($summary)->getContent(),
            'summary_text' => $summary,
        ];

        $email = (new Email())
            ->from(new Address($this->defaultFrom, 'Toastit'))
            ->to($user->getEmail())
            ->subject($this->buildReplySubject($originalSubject))
            ->html($this->twig->render('emails/todo_digest.html.twig', $context))
            ->text($this->twig->render('emails/todo_digest.txt.twig', $context));

        if (null !== $replyToAddress && '' !== trim($replyToAddress)) {
            $email->replyTo($replyToAddress);
        }

        $this->applyReplyHeaders($email, $messageId, $references);

        $this->mailer->send($email);
    }

    public function sendWeeklySummary(
        User $user,
        string $summary,
        ?string $originalSubject = null,
        ?string $messageId = null,
        ?string $references = null,
        ?string $replyToAddress = null,
    ): void {
        $summary = trim($summary);
        if ('' === $summary) {
            return;
        }

        $context = [
            'user' => $user,
            'summary_html' => $this->markdownConverter->convert($summary)->getContent(),
            'summary_text' => $summary,
        ];

        $email = (new Email())
            ->from(new Address($this->defaultFrom, 'Toastit'))
            ->to($user->getEmail())
            ->subject('' !== trim((string) $originalSubject) ? $this->buildReplySubject($originalSubject) : 'Toastit weekly operational summary')
            ->html($this->twig->render('emails/weekly_summary.html.twig', $context))
            ->text($this->twig->render('emails/weekly_summary.txt.twig', $context));

        if (null !== $replyToAddress && '' !== trim($replyToAddress)) {
            $email->replyTo($replyToAddress);
        }

        $this->applyReplyHeaders($email, $messageId, $references);
        $this->mailer->send($email);
    }

    /**
     * @param array{id: int, name: string, reason: string}|null $workspaceSuggestion
     */
    public function sendInboundToastAcknowledgement(
        Toast $toast,
        string $replyToAddress,
        ?array $workspaceSuggestion,
        bool $wasRewordedByAi,
        ?string $originalTitle,
        ?string $originalDescription,
        ?string $originalSubject = null,
        ?string $messageId = null,
        ?string $references = null,
    ): void {
        $context = [
            'toast' => $toast,
            'workspace_suggestion' => $workspaceSuggestion,
            'was_reworded_by_ai' => $wasRewordedByAi,
            'original_title' => trim((string) $originalTitle),
            'original_description' => trim((string) $originalDescription),
        ];

        $email = (new Email())
            ->from(new Address($this->defaultFrom, 'Toastit'))
            ->to($toast->getAuthor()->getEmail())
            ->replyTo($replyToAddress)
            ->subject($this->buildReplySubject($originalSubject ?: $toast->getTitle()))
            ->html($this->twig->render('emails/inbound_toast_acknowledgement.html.twig', $context))
            ->text($this->twig->render('emails/inbound_toast_acknowledgement.txt.twig', $context));

        $this->applyReplyHeaders($email, $messageId, $references);
        $this->mailer->send($email);
    }

    public function sendToastRephraseProposal(
        Toast $toast,
        string $proposedTitle,
        string $proposedDescription,
        ?string $replyToAddress = null,
        ?string $originalSubject = null,
        ?string $messageId = null,
        ?string $references = null,
    ): void {
        $context = [
            'toast' => $toast,
            'proposed_title' => $proposedTitle,
            'proposed_description_html' => $this->markdownConverter->convert(trim($proposedDescription))->getContent(),
            'proposed_description_text' => trim($proposedDescription),
        ];

        $email = (new Email())
            ->from(new Address($this->defaultFrom, 'Toastit'))
            ->to($toast->getAuthor()->getEmail())
            ->subject($this->buildReplySubject($originalSubject ?: $toast->getTitle()))
            ->html($this->twig->render('emails/toast_rephrase_proposal.html.twig', $context))
            ->text($this->twig->render('emails/toast_rephrase_proposal.txt.twig', $context));

        if (null !== $replyToAddress && '' !== trim($replyToAddress)) {
            $email->replyTo($replyToAddress);
        }

        $this->applyReplyHeaders($email, $messageId, $references);
        $this->mailer->send($email);
    }

    public function sendToastReplyActionResult(
        Toast $toast,
        array $actionResults,
        ?string $replyToAddress = null,
        ?string $originalSubject = null,
        ?string $messageId = null,
        ?string $references = null,
    ): void {
        $this->sendInboundActionSummary(
            $toast->getAuthor(),
            sprintf('Task #%d — %s', $toast->getId(), $toast->getTitle()),
            $actionResults,
            $replyToAddress,
            $toast,
            $originalSubject ?: sprintf('Task #%d', $toast->getId()),
            $messageId,
            $references,
        );
    }

    public function sendInboundActionSummary(
        User $recipient,
        string $contextLabel,
        array $actionResults,
        ?string $replyToAddress = null,
        ?Toast $toastSnapshot = null,
        ?string $originalSubject = null,
        ?string $messageId = null,
        ?string $references = null,
    ): void {
        $pendingCount = count(array_filter(
            $actionResults,
            static fn (array $result): bool => 'pending_confirmation' === ($result['status'] ?? null),
        ));
        $appliedCount = count(array_filter(
            $actionResults,
            static fn (array $result): bool => 'applied' === ($result['status'] ?? null),
        ));

        $context = [
            'context_label' => $contextLabel,
            'action_results' => $actionResults,
            'pending_count' => $pendingCount,
            'applied_count' => $appliedCount,
            'toast_snapshot' => $toastSnapshot,
        ];

        $email = (new Email())
            ->from(new Address($this->defaultFrom, 'Toastit'))
            ->to($recipient->getEmail())
            ->subject($this->buildReplySubject($originalSubject ?: $contextLabel))
            ->html($this->twig->render('emails/toast_reply_action_result.html.twig', $context))
            ->text($this->twig->render('emails/toast_reply_action_result.txt.twig', $context));

        if (null !== $replyToAddress && '' !== trim($replyToAddress)) {
            $email->replyTo($replyToAddress);
        }

        $this->applyReplyHeaders($email, $messageId, $references);
        $this->mailer->send($email);
    }
    /**
     * @param list<User> $recipients
     * @param array<int, string> $toastUrlsById
     */
    public function sendToastingSessionSummary(Workspace $workspace, ToastingSession $session, array $recipients, array $toastUrlsById = []): void
    {
        $summary = trim((string) $session->getSummary());
        if ('' === $summary) {
            return;
        }

        foreach ($recipients as $recipient) {
            $context = [
                'recipient' => $recipient,
                'workspace' => $workspace,
                'session' => $session,
                'summary_html' => $this->formatSessionSummaryHtml($summary, $toastUrlsById),
                'summary_text' => $this->formatSessionSummaryText($summary, $toastUrlsById),
            ];

            $email = (new Email())
                ->from(new Address($this->defaultFrom, 'Toastit'))
                ->to($recipient->getEmail())
                ->subject(sprintf('Toastit recap for %s session #%d', $workspace->getName(), $session->getId()))
                ->html($this->twig->render('emails/session_summary.html.twig', $context))
                ->text($this->twig->render('emails/session_summary.txt.twig', $context));

            $this->mailer->send($email);
        }
    }

    /**
     * @param array<int, string> $toastUrlsById
     */
    private function formatSessionSummaryHtml(string $summary, array $toastUrlsById): string
    {
        $markdownSummary = $this->replaceToastReferencesWithMarkdownLinks($summary, $toastUrlsById);

        return $this->markdownConverter->convert($markdownSummary)->getContent();
    }

    /**
     * @param array<int, string> $toastUrlsById
     */
    private function formatSessionSummaryText(string $summary, array $toastUrlsById): string
    {
        return $this->replaceToastReferencesWithPlainLinks($summary, $toastUrlsById);
    }

    /**
     * @param array<int, string> $toastUrlsById
     */
    private function replaceToastReferencesWithMarkdownLinks(string $value, array $toastUrlsById): string
    {
        return (string) preg_replace_callback(
            '/#\{?(\d+)\}?/',
            static function (array $matches) use ($toastUrlsById): string {
                $toastId = (int) $matches[1];
                $label = sprintf('#%d', $toastId);
                $url = $toastUrlsById[$toastId] ?? null;

                return null !== $url ? sprintf('[%s](%s)', $label, $url) : $label;
            },
            $value,
        );
    }

    /**
     * @param array<int, string> $toastUrlsById
     */
    private function replaceToastReferencesWithPlainLinks(string $value, array $toastUrlsById): string
    {
        return (string) preg_replace_callback(
            '/#\{?(\d+)\}?/',
            static function (array $matches) use ($toastUrlsById): string {
                $toastId = (int) $matches[1];
                $label = sprintf('#%d', $toastId);
                $url = $toastUrlsById[$toastId] ?? null;

                return null !== $url ? sprintf('%s (%s)', $label, $url) : $label;
            },
            $value,
        );
    }

    private function buildReplySubject(?string $originalSubject): string
    {
        $originalSubject = trim((string) $originalSubject);

        if ('' === $originalSubject) {
            return 'Toastit todo digest';
        }

        if (preg_match('/^re:/i', $originalSubject)) {
            return $originalSubject;
        }

        return sprintf('Re: %s', $originalSubject);
    }

    private function applyReplyHeaders(Email $email, ?string $messageId, ?string $references): void
    {
        if (null === $messageId || '' === trim($messageId)) {
            return;
        }

        $normalizedMessageId = trim($messageId);
        $normalizedReferences = trim(sprintf('%s %s', trim((string) $references), $normalizedMessageId));

        $email->getHeaders()->addTextHeader('In-Reply-To', $normalizedMessageId);
        $email->getHeaders()->addTextHeader('References', trim($normalizedReferences));
    }
}
