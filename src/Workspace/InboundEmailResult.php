<?php

namespace App\Workspace;

use App\Entity\Toast;

final class InboundEmailResult
{
    private function __construct(
        private readonly string $kind,
        private readonly ?Toast $toast = null,
    ) {
    }

    public static function toastCreated(Toast $toast): self
    {
        return new self('toast_created', $toast);
    }

    public static function todoDigestSent(): self
    {
        return new self('todo_digest_sent');
    }

    public function getKind(): string
    {
        return $this->kind;
    }

    public function getToast(): ?Toast
    {
        return $this->toast;
    }
}
