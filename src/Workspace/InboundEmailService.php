<?php

namespace App\Workspace;

use App\Entity\Toast;
use App\Entity\ToastReplyToken;
use App\Entity\User;
use App\Repository\WorkspaceRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use App\Mailer\TransactionalMailer;

final class InboundEmailService
{
    public function __construct(
        private readonly InboundEmailAddressService $inboundEmailAddress,
        private readonly UserRepository $userRepository,
        private readonly WorkspaceRepository $workspaceRepository,
        private readonly InboxWorkspaceService $inboxWorkspace,
        private readonly ToastCreationService $toastCreation,
        private readonly TodoDigestService $todoDigest,
        private readonly ToastDraftRefinementService $toastDraftRefinement,
        private readonly ToastReplyTokenService $toastReplyToken,
        private readonly InboundReplyAddressService $inboundReplyAddress,
        private readonly WorkspaceSuggestionService $workspaceSuggestion,
        private readonly TransactionalMailer $transactionalMailer,
        private readonly ToastTransferService $toastTransfer,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function ingest(
        string $recipient,
        string $from,
        ?string $subject = null,
        ?string $textBody = null,
        ?string $htmlBody = null,
        ?string $messageId = null,
        ?string $inReplyTo = null,
        ?string $references = null,
    ): ?InboundEmailResult {
        if (null !== $replyResult = $this->handleReplyRecipient($recipient, $subject, $textBody, $htmlBody, $messageId, $inReplyTo, $references)) {
            return $replyResult;
        }

        $userEmail = $this->inboundEmailAddress->resolveUserEmail($recipient);
        if (null === $userEmail) {
            return null;
        }

        $user = $this->userRepository->findOneByNormalizedEmail($userEmail);
        if (!$user instanceof User || $user->isDeleted()) {
            return null;
        }

        if ('todo' === mb_strtolower(trim((string) $subject))) {
            $this->todoDigest->sendTodoDigestReply(
                $user,
                $subject,
                $messageId ?? $inReplyTo,
                $references,
            );

            return InboundEmailResult::todoDigestSent();
        }

        $workspace = $this->inboxWorkspace->getOrCreateInboxWorkspace($user);
        $toast = $this->toastCreation->createToast(
            $workspace,
            $user,
            $this->buildTitle($from, $subject, $textBody, $htmlBody),
            $this->buildDescription($textBody, $htmlBody),
        );

        $this->entityManager->flush();

        $replyToken = $this->toastReplyToken->issue($user, $toast, ToastReplyToken::ACTION_REPHRASE);
        $replyToAddress = $this->inboundReplyAddress->buildAddress($replyToken->token->getSelector(), $replyToken->plainToken);
        $workspaceSuggestion = $this->workspaceSuggestion->suggestWorkspace($user, $toast->getTitle(), $toast->getDescription());

        if (null !== $replyToAddress) {
            $this->transactionalMailer->sendInboundToastAcknowledgement(
                $toast,
                $replyToAddress,
                $workspaceSuggestion,
                $subject,
                $messageId,
                $references,
            );
        }

        return InboundEmailResult::toastCreated($toast);
    }

    private function handleReplyRecipient(
        string $recipient,
        ?string $subject,
        ?string $textBody,
        ?string $htmlBody,
        ?string $messageId,
        ?string $inReplyTo,
        ?string $references,
    ): ?InboundEmailResult {
        $replyRecipient = $this->inboundReplyAddress->parseAddress($recipient);

        if (null === $replyRecipient) {
            return null;
        }

        $replyToken = $this->toastReplyToken->findValid($replyRecipient['selector'], $replyRecipient['token']);

        if (!$replyToken instanceof ToastReplyToken) {
            return null;
        }

        $replyBody = mb_strtolower($this->extractBodyText($textBody, $htmlBody));
        $shouldTransfer = 1 === preg_match('/\btransfer\b/', $replyBody);
        $shouldReword = 1 === preg_match('/\b(reword|yes)\b/', $replyBody);

        if (!$shouldTransfer && !$shouldReword) {
            return InboundEmailResult::todoDigestSent();
        }

        $this->toastReplyToken->markUsed($replyToken);

        $toast = $replyToken->getToast();
        $actor = $replyToken->getUser();
        $workspaceSuggestion = $this->workspaceSuggestion->suggestWorkspace($actor, $toast->getTitle(), $toast->getDescription());
        $transferApplied = false;
        $rewordApplied = false;
        $proposedTitle = null;
        $proposedDescription = null;

        if (
            $shouldTransfer
            && null !== $workspaceSuggestion
            && $workspaceSuggestion['id'] !== $toast->getWorkspace()->getId()
        ) {
            $targetWorkspace = $this->workspaceAccessWorkspaceOrNull((int) $workspaceSuggestion['id'], $actor);

            if (null !== $targetWorkspace) {
                $toast = $this->toastTransfer->transfer($toast, $targetWorkspace, $actor);
                $transferApplied = true;
            }
        }

        if ($shouldReword) {
            $proposal = $this->toastDraftRefinement->refine(
                $toast->getWorkspace(),
                $toast->getTitle(),
                $toast->getDescription(),
            );

            $proposedTitle = $proposal['title'];
            $proposedDescription = $proposal['description'];
            $rewordApplied = true;
        }

        $this->entityManager->flush();

        $this->transactionalMailer->sendToastReplyActionResult(
            $toast,
            $rewordApplied,
            $transferApplied,
            $workspaceSuggestion,
            $proposedTitle,
            $proposedDescription,
            $subject,
            $messageId ?? $inReplyTo,
            $references,
        );

        return InboundEmailResult::todoDigestSent();
    }

    private function buildTitle(string $from, ?string $subject, ?string $textBody, ?string $htmlBody): string
    {
        $subject = trim((string) $subject);
        if ('' !== $subject) {
            return $this->truncate($subject, 180);
        }

        $preview = $this->extractPreviewText($textBody, $htmlBody);
        if ('' !== $preview) {
            return $this->truncate($preview, 180);
        }

        return $this->truncate(sprintf('Email from %s', trim($from)), 180);
    }

    private function buildDescription(?string $textBody, ?string $htmlBody): ?string
    {
        $body = $this->extractBodyText($textBody, $htmlBody);

        return '' !== $body ? $body : '(No email body provided)';
    }

    private function extractPreviewText(?string $textBody, ?string $htmlBody): string
    {
        $body = $this->extractBodyText($textBody, $htmlBody);
        if ('' === $body) {
            return '';
        }

        $lines = preg_split('/\R+/', $body) ?: [];

        foreach ($lines as $line) {
            $line = trim($line);
            if ('' !== $line) {
                return $line;
            }
        }

        return '';
    }

    private function extractBodyText(?string $textBody, ?string $htmlBody): string
    {
        $textBody = trim((string) $textBody);
        if ('' !== $textBody) {
            return $textBody;
        }

        $htmlBody = trim((string) $htmlBody);
        if ('' === $htmlBody) {
            return '';
        }

        $normalizedHtml = preg_replace('/<br\s*\/?>/i', "\n", $htmlBody) ?? $htmlBody;
        $normalizedHtml = preg_replace('/<\/p>/i', "</p>\n", $normalizedHtml) ?? $normalizedHtml;
        $text = html_entity_decode(strip_tags($normalizedHtml), ENT_QUOTES | ENT_HTML5);
        $text = preg_replace("/\n{3,}/", "\n\n", $text ?? '') ?? '';

        return trim($text);
    }

    private function truncate(string $value, int $maxLength): string
    {
        return mb_strlen($value) <= $maxLength
            ? $value
            : rtrim(mb_substr($value, 0, $maxLength - 1)).'…';
    }

    private function workspaceAccessWorkspaceOrNull(int $workspaceId, User $user): ?\App\Entity\Workspace
    {
        return $this->workspaceRepository->findOneForUser($workspaceId, $user);
    }
}
