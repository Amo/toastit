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
        private readonly ToastTitleNormalizationService $toastTitleNormalization,
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
        $normalizedTitle = $this->toastTitleNormalization->normalize($title, $description);

        $toast = (new Toast())
            ->setWorkspace($workspace)
            ->setAuthor($author)
            ->setTitle($normalizedTitle['title'])
            ->setDescription($normalizedTitle['description'])
            ->setOwner($owner)
            ->setDueAt($dueAt)
            ->setPreviousItem($previousItem);

        $this->entityManager->persist($toast);

        return $toast;
    }
}
