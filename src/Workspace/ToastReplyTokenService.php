<?php

namespace App\Workspace;

use App\Entity\Toast;
use App\Entity\ToastReplyToken;
use App\Entity\User;
use App\Repository\ToastReplyTokenRepository;
use Doctrine\ORM\EntityManagerInterface;

final class ToastReplyTokenService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ToastReplyTokenRepository $replyTokenRepository,
    ) {
    }

    public function issue(User $user, Toast $toast, string $action): ToastReplyTokenResult
    {
        $now = new \DateTimeImmutable();
        $this->replyTokenRepository->invalidateActiveTokens($user, (int) $toast->getId(), $action, $now);

        $selector = bin2hex(random_bytes(8));
        $token = bin2hex(random_bytes(16));

        $replyToken = (new ToastReplyToken())
            ->setUser($user)
            ->setToast($toast)
            ->setAction($action)
            ->setSelector($selector)
            ->setTokenHash(hash('sha256', $token))
            ->setExpiresAt($now->modify('+7 days'));

        $this->entityManager->persist($replyToken);
        $this->entityManager->flush();

        return new ToastReplyTokenResult($replyToken, $token);
    }

    public function consume(string $selector, string $token): ?ToastReplyToken
    {
        $replyToken = $this->findValid($selector, $token);

        if (!$replyToken instanceof ToastReplyToken) {
            return null;
        }

        $replyToken->setUsedAt(new \DateTimeImmutable());
        $this->entityManager->flush();

        return $replyToken;
    }

    public function findValid(string $selector, string $token): ?ToastReplyToken
    {
        $replyToken = $this->replyTokenRepository->findActiveBySelector($selector, new \DateTimeImmutable());

        if (!$replyToken instanceof ToastReplyToken) {
            return null;
        }

        if (!hash_equals($replyToken->getTokenHash(), hash('sha256', $token))) {
            return null;
        }

        return $replyToken;
    }

    public function markUsed(ToastReplyToken $replyToken): void
    {
        $replyToken->setUsedAt(new \DateTimeImmutable());
        $this->entityManager->flush();
    }
}
