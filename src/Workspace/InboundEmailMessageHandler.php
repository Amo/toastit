<?php

namespace App\Workspace;

use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class InboundEmailMessageHandler
{
    public function __construct(
        private InboundEmailService $inboundEmail,
    ) {
    }

    public function __invoke(InboundEmailMessage $message): void
    {
        $this->inboundEmail->ingest(
            $message->recipient,
            $message->from,
            $message->subject,
            $message->text,
            $message->html,
            $message->messageId,
            $message->inReplyTo,
            $message->references,
        );
    }
}
