<?php

namespace App\Workspace;

use App\Entity\User;
use App\Entity\Workspace;
use App\Entity\WorkspaceNote;
use App\Entity\WorkspaceNoteVersion;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class WorkspaceNoteService
{
    private const HISTORY_MUTATION_DAYS_TO_KEEP = 5;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function createNote(
        Workspace $workspace,
        User $actor,
        string $title,
        ?string $body = null,
        bool $isImportant = false,
        ?\DateTimeImmutable $recordedAt = null,
    ): WorkspaceNote {
        $recordedAt ??= new \DateTimeImmutable();

        $note = (new WorkspaceNote())
            ->setWorkspace($workspace)
            ->setAuthor($actor)
            ->setCreatedAt($recordedAt)
            ->applySnapshot($title, $body, $isImportant, $recordedAt);

        $workspace->addNote($note);
        $this->entityManager->persist($note);
        $this->recordSnapshot($note, $actor, $recordedAt);

        return $note;
    }

    public function updateNote(
        WorkspaceNote $note,
        User $actor,
        string $title,
        ?string $body = null,
        bool $isImportant = false,
        ?\DateTimeImmutable $recordedAt = null,
    ): WorkspaceNote {
        if ($note->matchesSnapshot($title, $body, $isImportant)) {
            return $note;
        }

        $recordedAt ??= new \DateTimeImmutable();
        $note->applySnapshot($title, $body, $isImportant, $recordedAt);
        $this->recordSnapshot($note, $actor, $recordedAt);

        return $note;
    }

    public function revertToVersion(
        WorkspaceNote $note,
        WorkspaceNoteVersion $version,
        User $actor,
        ?\DateTimeImmutable $recordedAt = null,
    ): WorkspaceNote {
        if ($version->getNote() !== $note) {
            throw new NotFoundHttpException();
        }

        return $this->updateNote(
            $note,
            $actor,
            $version->getTitle(),
            $version->getBody(),
            $version->isImportant(),
            $recordedAt,
        );
    }

    public function deleteNote(WorkspaceNote $note, User $actor): void
    {
        if (!$this->canDelete($note, $actor)) {
            throw new AccessDeniedHttpException();
        }

        $this->entityManager->remove($note);
    }

    public function transferNote(WorkspaceNote $note, Workspace $targetWorkspace, User $actor): WorkspaceNote
    {
        if (!$note->getWorkspace()->isOwnedBy($actor) && !$actor->isRoot()) {
            throw new AccessDeniedHttpException();
        }

        $sourceWorkspace = $note->getWorkspace();
        if ($sourceWorkspace->getId() === $targetWorkspace->getId()) {
            throw new \InvalidArgumentException('invalid_target_workspace');
        }

        $sourceWorkspace->removeNote($note);
        $targetWorkspace->addNote($note);

        return $note;
    }

    public function canDelete(WorkspaceNote $note, User $actor): bool
    {
        if ($actor->isRoot()) {
            return true;
        }

        if ($note->getAuthor()->getId() === $actor->getId()) {
            return true;
        }

        return $note->getWorkspace()->isOwnedBy($actor);
    }

    private function recordSnapshot(WorkspaceNote $note, User $actor, \DateTimeImmutable $recordedAt): void
    {
        $latestVersion = $this->resolveLatestVersion($note);
        if ($latestVersion instanceof WorkspaceNoteVersion
            && $latestVersion->getTitle() === $note->getTitle()
            && $latestVersion->getBody() === $note->getBody()
            && $latestVersion->isImportant() === $note->isImportant()) {
            return;
        }

        $version = (new WorkspaceNoteVersion())
            ->setAuthor($actor)
            ->setTitle($note->getTitle())
            ->setBody($note->getBody())
            ->setIsImportant($note->isImportant())
            ->setRecordedAt($recordedAt);

        $note->addVersion($version);
        $this->entityManager->persist($version);
        $this->pruneHistory($note);
    }

    private function pruneHistory(WorkspaceNote $note): void
    {
        $versions = $note->getVersions()->toArray();
        usort(
            $versions,
            static fn (WorkspaceNoteVersion $left, WorkspaceNoteVersion $right): int => $right->getRecordedAt() <=> $left->getRecordedAt()
        );

        $keptMutationDays = [];

        foreach ($versions as $version) {
            $mutationDay = $version->getRecordedAt()->format('Y-m-d');
            if (!isset($keptMutationDays[$mutationDay]) && count($keptMutationDays) < self::HISTORY_MUTATION_DAYS_TO_KEEP) {
                $keptMutationDays[$mutationDay] = true;
            }

            if (isset($keptMutationDays[$mutationDay])) {
                continue;
            }

            $note->removeVersion($version);
            $this->entityManager->remove($version);
        }
    }

    private function resolveLatestVersion(WorkspaceNote $note): ?WorkspaceNoteVersion
    {
        $latestVersion = null;

        foreach ($note->getVersions() as $version) {
            if (!$latestVersion instanceof WorkspaceNoteVersion || $version->getRecordedAt() > $latestVersion->getRecordedAt()) {
                $latestVersion = $version;
            }
        }

        return $latestVersion;
    }
}
