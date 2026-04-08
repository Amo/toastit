<?php

namespace App\Workspace;

use App\Entity\User;
use App\Repository\UserRepository;

final class InboundRecipientAccessService
{
    public function __construct(
        private readonly InboundEmailAddressService $inboundEmailAddress,
        private readonly InboundReplyAddressService $inboundReplyAddress,
        private readonly UserRepository $userRepository,
        private readonly ToastReplyTokenService $toastReplyToken,
    ) {
    }

    public function isAccepted(string $recipient): bool
    {
        $recipient = trim($recipient);
        if ('' === $recipient) {
            return false;
        }

        $replyRecipient = $this->inboundReplyAddress->parseAddress($recipient);
        if (null !== $replyRecipient) {
            return null !== $this->toastReplyToken->findValid($replyRecipient['selector'], $replyRecipient['token']);
        }

        $user = $this->resolveUser($recipient);

        return $user instanceof User && !$user->isDeleted();
    }

    private function resolveUser(string $recipient): ?User
    {
        $userAlias = $this->inboundEmailAddress->resolveUserAlias($recipient);
        if (null !== $userAlias) {
            $candidateUser = $this->userRepository->findOneByInboundEmailAlias($userAlias);

            return $candidateUser instanceof User ? $candidateUser : null;
        }

        $userEmail = $this->inboundEmailAddress->resolveUserEmail($recipient);
        if (null === $userEmail) {
            return null;
        }

        $candidateUser = $this->userRepository->findOneByNormalizedEmail($userEmail);

        return $candidateUser instanceof User ? $candidateUser : null;
    }
}
