<?php

namespace App\Workspace;

final readonly class InboundEmailMessage
{
    public function __construct(
        public string $recipient,
        public string $from,
        public ?string $subject = null,
        public ?string $text = null,
        public ?string $html = null,
        public ?string $messageId = null,
        public ?string $inReplyTo = null,
        public ?string $references = null,
    ) {
    }
}
