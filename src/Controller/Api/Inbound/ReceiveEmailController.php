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
        $recipient = trim((string) ($payload['recipient'] ?? ''));
        $from = trim((string) ($payload['from'] ?? ''));

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
}
