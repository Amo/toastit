<?php

namespace App\Entity;

use App\Repository\WorkspaceNoteRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: WorkspaceNoteRepository::class)]
#[ORM\Table(name: 'workspace_note')]
class WorkspaceNote
{
    public const TITLE_MAX_LENGTH = 255;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Workspace::class, inversedBy: 'notes')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Workspace $workspace;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $author;

    #[ORM\Column(length: self::TITLE_MAX_LENGTH)]
    private string $title = '';

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $body = null;

    #[ORM\Column(options: ['default' => false])]
    private bool $isImportant = false;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $updatedAt;

    /** @var Collection<int, WorkspaceNoteVersion> */
    #[ORM\OneToMany(mappedBy: 'note', targetEntity: WorkspaceNoteVersion::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['recordedAt' => 'DESC', 'id' => 'DESC'])]
    private Collection $versions;

    public function __construct()
    {
        $now = new \DateTimeImmutable();
        $this->createdAt = $now;
        $this->updatedAt = $now;
        $this->versions = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getWorkspace(): Workspace
    {
        return $this->workspace;
    }

    public function setWorkspace(Workspace $workspace): self
    {
        $this->workspace = $workspace;

        return $this;
    }

    public function getAuthor(): User
    {
        return $this->author;
    }

    public function setAuthor(User $author): self
    {
        $this->author = $author;

        return $this;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title, ?\DateTimeImmutable $updatedAt = null): self
    {
        $this->title = $this->normalizeTitle($title);
        $this->updatedAt = $updatedAt ?? new \DateTimeImmutable();

        return $this;
    }

    public function getBody(): ?string
    {
        return $this->body;
    }

    public function setBody(?string $body, ?\DateTimeImmutable $updatedAt = null): self
    {
        $this->body = $this->normalizeBody($body);
        $this->updatedAt = $updatedAt ?? new \DateTimeImmutable();

        return $this;
    }

    public function isImportant(): bool
    {
        return $this->isImportant;
    }

    public function setIsImportant(bool $isImportant, ?\DateTimeImmutable $updatedAt = null): self
    {
        $this->isImportant = $isImportant;
        $this->updatedAt = $updatedAt ?? new \DateTimeImmutable();

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): self
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(\DateTimeImmutable $updatedAt): self
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }

    public function matchesSnapshot(string $title, ?string $body, bool $isImportant): bool
    {
        return $this->title === $this->normalizeTitle($title)
            && $this->body === $this->normalizeBody($body)
            && $this->isImportant === $isImportant;
    }

    public function applySnapshot(string $title, ?string $body, bool $isImportant, ?\DateTimeImmutable $updatedAt = null): self
    {
        $updatedAt ??= new \DateTimeImmutable();
        $this->title = $this->normalizeTitle($title);
        $this->body = $this->normalizeBody($body);
        $this->isImportant = $isImportant;
        $this->updatedAt = $updatedAt;

        return $this;
    }

    /** @return Collection<int, WorkspaceNoteVersion> */
    public function getVersions(): Collection
    {
        return $this->versions;
    }

    public function addVersion(WorkspaceNoteVersion $version): self
    {
        if (!$this->versions->contains($version)) {
            $this->versions->add($version);
            $version->setNote($this);
        }

        return $this;
    }

    public function removeVersion(WorkspaceNoteVersion $version): self
    {
        $this->versions->removeElement($version);

        return $this;
    }

    private function normalizeTitle(string $title): string
    {
        return mb_substr(trim($title), 0, self::TITLE_MAX_LENGTH);
    }

    private function normalizeBody(?string $body): ?string
    {
        $body = null !== $body ? trim($body) : null;

        return '' === $body ? null : $body;
    }
}
