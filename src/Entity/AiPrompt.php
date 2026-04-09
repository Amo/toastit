<?php

namespace App\Entity;

use App\Repository\AiPromptRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: AiPromptRepository::class)]
#[ORM\Table(name: 'ai_prompt')]
class AiPrompt
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 120, unique: true)]
    private string $code = '';

    #[ORM\Column(length: 180)]
    private string $label = '';

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description = null;

    /** @var list<array{name: string, description: string, example: string}> */
    #[ORM\Column(type: 'json')]
    private array $availableVariables = [];

    /** @var list<array{name: string, description: string, example: string}> */
    #[ORM\Column(type: 'json')]
    private array $availableUserVariables = [];

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $updatedAt;

    /** @var Collection<int, AiPromptVersion> */
    #[ORM\OneToMany(mappedBy: 'prompt', targetEntity: AiPromptVersion::class, cascade: ['remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['versionNumber' => 'DESC'])]
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

    public function getCode(): string
    {
        return $this->code;
    }

    public function setCode(string $code): self
    {
        $this->code = trim($code);

        return $this;
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function setLabel(string $label): self
    {
        $this->label = trim($label);

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): self
    {
        $this->description = null !== $description ? trim($description) : null;

        return $this;
    }

    /**
     * @return list<array{name: string, description: string, example: string}>
     */
    public function getAvailableVariables(): array
    {
        return $this->availableVariables;
    }

    /**
     * @param list<array{name: string, description: string, example: string}> $availableVariables
     */
    public function setAvailableVariables(array $availableVariables): self
    {
        $this->availableVariables = array_values($availableVariables);

        return $this;
    }

    /**
     * @return list<array{name: string, description: string, example: string}>
     */
    public function getAvailableUserVariables(): array
    {
        return $this->availableUserVariables;
    }

    /**
     * @param list<array{name: string, description: string, example: string}> $availableUserVariables
     */
    public function setAvailableUserVariables(array $availableUserVariables): self
    {
        $this->availableUserVariables = array_values($availableUserVariables);

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
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

    /** @return Collection<int, AiPromptVersion> */
    public function getVersions(): Collection
    {
        return $this->versions;
    }

    public function addVersion(AiPromptVersion $version): self
    {
        if (!$this->versions->contains($version)) {
            $this->versions->add($version);
            $version->setPrompt($this);
        }

        return $this;
    }
}
