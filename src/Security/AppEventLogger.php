<?php

namespace App\Security;

use App\Entity\AppEvent;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

final class AppEventLogger
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @param array<string, mixed> $metadata
     */
    public function log(
        string $kind,
        ?int $userId = null,
        ?string $actorEmail = null,
        ?string $source = null,
        ?string $status = null,
        array $metadata = [],
    ): void {
        $event = (new AppEvent())
            ->setKind($kind)
            ->setStatus($status)
            ->setSource($source)
            ->setActorEmail($actorEmail)
            ->setMetadata($metadata);

        if (null !== $userId) {
            $event->setUser($this->entityManager->getReference(User::class, $userId));
        }

        $this->entityManager->persist($event);
        $this->entityManager->flush();
    }
}
