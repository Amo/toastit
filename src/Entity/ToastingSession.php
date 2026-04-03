<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'toasting_session')]
class ToastingSession
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Workspace::class, inversedBy: 'toastingSessions')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Workspace $workspace;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $startedBy;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $endedBy = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $startedAt;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $endedAt = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $summary = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $summaryGeneratedAt = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $summaryUpdatedAt = null;

    public function __construct()
    {
        $this->startedAt = new \DateTimeImmutable();
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

    public function getStartedBy(): User
    {
        return $this->startedBy;
    }

    public function setStartedBy(User $startedBy): self
    {
        $this->startedBy = $startedBy;

        return $this;
    }

    public function getEndedBy(): ?User
    {
        return $this->endedBy;
    }

    public function setEndedBy(?User $endedBy): self
    {
        $this->endedBy = $endedBy;

        return $this;
    }

    public function getStartedAt(): \DateTimeImmutable
    {
        return $this->startedAt;
    }

    public function setStartedAt(\DateTimeImmutable $startedAt): self
    {
        $this->startedAt = $startedAt;

        return $this;
    }

    public function getEndedAt(): ?\DateTimeImmutable
    {
        return $this->endedAt;
    }

    public function setEndedAt(?\DateTimeImmutable $endedAt): self
    {
        $this->endedAt = $endedAt;

        return $this;
    }

    public function isActive(): bool
    {
        return null === $this->endedAt;
    }

    public function getSummary(): ?string
    {
        return $this->summary;
    }

    public function setSummary(?string $summary, ?\DateTimeImmutable $updatedAt = null): self
    {
        $summary = null !== $summary ? trim($summary) : null;
        $this->summary = '' === $summary ? null : $summary;
        $this->summaryUpdatedAt = null !== $this->summary ? ($updatedAt ?? new \DateTimeImmutable()) : null;

        return $this;
    }

    public function hasSummary(): bool
    {
        return null !== $this->summary && '' !== trim($this->summary);
    }

    public function getSummaryGeneratedAt(): ?\DateTimeImmutable
    {
        return $this->summaryGeneratedAt;
    }

    public function setSummaryGeneratedAt(?\DateTimeImmutable $summaryGeneratedAt): self
    {
        $this->summaryGeneratedAt = $summaryGeneratedAt;

        return $this;
    }

    public function getSummaryUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->summaryUpdatedAt;
    }

    public function setSummaryUpdatedAt(?\DateTimeImmutable $summaryUpdatedAt): self
    {
        $this->summaryUpdatedAt = $summaryUpdatedAt;

        return $this;
    }
}
