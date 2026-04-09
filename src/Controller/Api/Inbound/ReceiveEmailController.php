<?php

namespace App\Controller\Api\Inbound;

use App\Workspace\InboundEmailMessage;
use App\Workspace\InboundRecipientAccessService;
use App\Security\AppEventLogger;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;

final class ReceiveEmailController extends AbstractController
{
    public function __construct(
        private readonly MessageBusInterface $messageBus,
        private readonly InboundRecipientAccessService $inboundRecipientAccess,
        private readonly AppEventLogger $eventLogger,
        private readonly string $inboundEmailSecret,
    ) {
    }

    #[Route('/api/inbound/email', name: 'api_inbound_email_receive', methods: ['POST'])]
    public function __invoke(Request $request): JsonResponse
    {
        if (!$this->isSecretValid((string) $request->headers->get('X-Toastit-Inbound-Secret'))) {
            return $this->json(['ok' => false, 'error' => 'forbidden'], 403);
        }

        $payload = $request->toArray();
        $recipient = $this->resolveRecipient($payload);
        $from = $this->resolveSender($payload);

        if ('' === $recipient || '' === $from) {
            return $this->json(['ok' => false, 'error' => 'missing_email_metadata'], 400);
        }

        if (!$this->inboundRecipientAccess->isAccepted($recipient)) {
            $this->eventLogger->log('inbound.email_rejected', null, $from, 'inbound_email', 'unknown_recipient', [
                'recipient' => $recipient,
            ]);
            return $this->json(['ok' => false, 'error' => 'unknown_inbox'], 404);
        }

        $this->messageBus->dispatch(new InboundEmailMessage(
            $recipient,
            $from,
            isset($payload['subject']) ? (string) $payload['subject'] : null,
            isset($payload['text']) ? (string) $payload['text'] : null,
            isset($payload['html']) ? (string) $payload['html'] : null,
            isset($payload['messageId']) ? (string) $payload['messageId'] : null,
            isset($payload['inReplyTo']) ? (string) $payload['inReplyTo'] : null,
            isset($payload['references']) ? (string) $payload['references'] : null,
        ));
        $this->eventLogger->log('inbound.email_received', null, $from, 'inbound_email', 'queued', [
            'recipient' => $recipient,
        ]);

        return $this->json([
            'ok' => true,
            'kind' => 'queued',
        ], 202);
    }

    private function isSecretValid(string $providedSecret): bool
    {
        return '' !== $this->inboundEmailSecret
            && hash_equals($this->inboundEmailSecret, $providedSecret);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function resolveRecipient(array $payload): string
    {
        $candidate = $this->resolvePayloadValue($payload, [
            'recipient',
            'to',
            'envelope.to',
            'envelope.rcpt_tos',
        ]);

        return $this->normalizeMailbox($candidate, true);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function resolveSender(array $payload): string
    {
        $candidate = $this->resolvePayloadValue($payload, [
            'from',
            'sender',
            'mailFrom',
            'envelope.from',
            'envelope.mail_from',
        ]);

        return $this->normalizeMailbox($candidate, false);
    }

    /**
     * @param array<string, mixed> $payload
     * @param list<string> $paths
     */
    private function resolvePayloadValue(array $payload, array $paths): mixed
    {
        foreach ($paths as $path) {
            $value = $this->readPathValue($payload, $path);
            if (null !== $value && '' !== trim((string) $value)) {
                return $value;
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function readPathValue(array $payload, string $path): mixed
    {
        $segments = explode('.', $path);
        $current = $payload;

        foreach ($segments as $segment) {
            if (!is_array($current)) {
                return null;
            }

            if (!array_key_exists($segment, $current)) {
                return null;
            }

            $current = $current[$segment];
        }

        if (is_array($current)) {
            foreach ($current as $candidate) {
                if (is_scalar($candidate) && '' !== trim((string) $candidate)) {
                    return $candidate;
                }
            }

            return null;
        }

        return $current;
    }

    private function normalizeMailbox(mixed $value, bool $strict): string
    {
        if (null === $value) {
            return '';
        }

        $candidate = trim((string) $value);
        if ('' === $candidate) {
            return '';
        }

        if (preg_match('/<([^<>]+)>/', $candidate, $matches)) {
            $candidate = trim((string) $matches[1]);
        }

        if (str_starts_with(mb_strtolower($candidate), 'mailto:')) {
            $candidate = trim(substr($candidate, 7));
        }

        $candidate = trim($candidate, " \t\n\r\0\x0B\"'");
        if ('' === $candidate) {
            return '';
        }

        if (false !== filter_var($candidate, FILTER_VALIDATE_EMAIL)) {
            return $candidate;
        }

        return $strict ? '' : $candidate;
    }
}
