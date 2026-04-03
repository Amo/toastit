<?php

namespace App\Workspace;

use App\Entity\Toast;
use App\Entity\User;
use App\Entity\Workspace;
use Doctrine\ORM\EntityManagerInterface;

final class ToastCreationService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function createToast(
        Workspace $workspace,
        User $author,
        string $title,
        ?string $description = null,
        ?User $owner = null,
        ?\DateTimeImmutable $dueAt = null,
        ?Toast $previousItem = null,
    ): Toast {
        $toast = (new Toast())
            ->setWorkspace($workspace)
            ->setAuthor($author)
            ->setTitle(trim($title))
            ->setDescription($this->normalizeNullableText($description))
            ->setOwner($owner)
            ->setDueAt($dueAt)
            ->setPreviousItem($previousItem);

        $this->entityManager->persist($toast);

        return $toast;
    }

    private function normalizeNullableText(?string $value): ?string
    {
        $value = null !== $value ? trim($value) : null;

        return '' === $value ? null : $value;
    }
}
