<?php

namespace App\Entity;

use App\Repository\AiPromptVersionRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: AiPromptVersionRepository::class)]
#[ORM\Table(name: 'ai_prompt_version')]
class AiPromptVersion
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: AiPrompt::class, inversedBy: 'versions')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private AiPrompt $prompt;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $changedByUser = null;

    #[ORM\Column]
    private int $versionNumber = 1;

    #[ORM\Column(type: 'text')]
    private string $systemPrompt = '';

    #[ORM\Column(type: 'text')]
    private string $userPromptTemplate = '';

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $changedAt;

    public function __construct()
    {
        $this->changedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getPrompt(): AiPrompt
    {
        return $this->prompt;
    }

    public function setPrompt(AiPrompt $prompt): self
    {
        $this->prompt = $prompt;

        return $this;
    }

    public function getChangedByUser(): ?User
    {
        return $this->changedByUser;
    }

    public function setChangedByUser(?User $changedByUser): self
    {
        $this->changedByUser = $changedByUser;

        return $this;
    }

    public function getVersionNumber(): int
    {
        return $this->versionNumber;
    }

    public function setVersionNumber(int $versionNumber): self
    {
        $this->versionNumber = max(1, $versionNumber);

        return $this;
    }

    public function getSystemPrompt(): string
    {
        return $this->systemPrompt;
    }

    public function setSystemPrompt(string $systemPrompt): self
    {
        $this->systemPrompt = trim($systemPrompt);

        return $this;
    }

    public function getChangedAt(): \DateTimeImmutable
    {
        return $this->changedAt;
    }

    public function setChangedAt(\DateTimeImmutable $changedAt): self
    {
        $this->changedAt = $changedAt;

        return $this;
    }

    public function getUserPromptTemplate(): string
    {
        return $this->userPromptTemplate;
    }

    public function setUserPromptTemplate(string $userPromptTemplate): self
    {
        $this->userPromptTemplate = trim($userPromptTemplate);

        return $this;
    }
}
