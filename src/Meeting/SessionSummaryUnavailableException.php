<?php

namespace App\Meeting;

final class SessionSummaryUnavailableException extends \RuntimeException
{
    public function __construct(
        private readonly string $reason,
        string $message,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }

    public function getReason(): string
    {
        return $this->reason;
    }
}
