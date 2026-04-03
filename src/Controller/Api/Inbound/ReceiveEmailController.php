<?php

namespace App\Controller\Api\Inbound;

use App\Workspace\InboundEmailService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

final class ReceiveEmailController extends AbstractController
{
    public function __construct(
        private readonly InboundEmailService $inboundEmail,
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

        $result = $this->inboundEmail->ingest(
            $recipient,
            $from,
            isset($payload['subject']) ? (string) $payload['subject'] : null,
            isset($payload['text']) ? (string) $payload['text'] : null,
            isset($payload['html']) ? (string) $payload['html'] : null,
            isset($payload['messageId']) ? (string) $payload['messageId'] : null,
            isset($payload['inReplyTo']) ? (string) $payload['inReplyTo'] : null,
            isset($payload['references']) ? (string) $payload['references'] : null,
        );

        if (null === $result) {
            return $this->json(['ok' => false, 'error' => 'unknown_inbox'], 404);
        }

        if ('todo_digest_sent' === $result->getKind()) {
            return $this->json([
                'ok' => true,
                'kind' => 'todo_digest_sent',
            ]);
        }

        $toast = $result->getToast();

        return $this->json([
            'ok' => true,
            'kind' => 'toast_created',
            'itemId' => $toast->getId(),
            'workspaceId' => $toast->getWorkspace()->getId(),
        ]);
    }

    private function isSecretValid(string $providedSecret): bool
    {
        return '' !== $this->inboundEmailSecret
            && hash_equals($this->inboundEmailSecret, $providedSecret);
    }
}
