<?php

namespace App\Workspace;

use App\Entity\ToastReplyToken;

final readonly class ToastReplyTokenResult
{
    public function __construct(
        public ToastReplyToken $token,
        public string $plainToken,
    ) {
    }
}
