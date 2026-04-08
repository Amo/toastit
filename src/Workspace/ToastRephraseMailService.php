<?php

namespace App\Workspace;

use App\Entity\Toast;
use App\Mailer\TransactionalMailer;

final class ToastRephraseMailService
{
    public function __construct(
        private readonly ToastDraftRefinementService $toastDraftRefinement,
        private readonly TransactionalMailer $transactionalMailer,
    ) {
    }

    public function sendRephraseProposal(
        Toast $toast,
        ?string $originalSubject,
        ?string $messageId,
        ?string $references,
    ): void {
        $proposal = $this->toastDraftRefinement->refine(
            $toast->getWorkspace(),
            $toast->getTitle(),
            $toast->getDescription(),
            $toast->getAuthor(),
        );

        $this->transactionalMailer->sendToastRephraseProposal(
            $toast,
            $proposal['title'],
            $proposal['description'],
            $originalSubject,
            $messageId,
            $references,
        );
    }
}
