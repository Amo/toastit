<?php

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'parking_lot_item')]
class Toast
{
    public const STATUS_OPEN = 'open';
    public const STATUS_VETOED = 'vetoed';
    public const DISCUSSION_PENDING = 'pending';
    public const DISCUSSION_TREATED = 'treated';
    public const DISCUSSION_POSTPONED = 'postponed';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Workspace::class, inversedBy: 'items')]
    #[ORM\JoinColumn(name: 'team_id', nullable: false, onDelete: 'CASCADE')]
    private Workspace $workspace;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $author;

    #[ORM\Column(length: 180)]
    private string $title = '';

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description = null;

    #[ORM\Column(length: 16)]
    private string $status = self::STATUS_OPEN;

    #[ORM\Column]
    private bool $isBoosted = false;

    #[ORM\Column(nullable: true)]
    private ?int $boostRank = null;

    #[ORM\Column(length: 16)]
    private string $discussionStatus = self::DISCUSSION_PENDING;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $discussionNotes = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $followUp = null;

    #[ORM\Column(type: 'json', options: ['default' => '[]'])]
    private array $followUpItems = [];

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $owner = null;

    #[ORM\Column(type: 'date_immutable', nullable: true)]
    private ?\DateTimeImmutable $dueAt = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\ManyToOne(targetEntity: self::class, inversedBy: 'followUpChildren')]
    #[ORM\JoinColumn(name: 'previous_item_id', nullable: true, onDelete: 'SET NULL')]
    private ?self $previousItem = null;

    /** @var Collection<int, self> */
    #[ORM\OneToMany(mappedBy: 'previousItem', targetEntity: self::class)]
    private Collection $followUpChildren;

    /** @var Collection<int, Vote> */
    #[ORM\OneToMany(mappedBy: 'item', targetEntity: Vote::class, cascade: ['remove'], orphanRemoval: true)]
    private Collection $votes;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->votes = new ArrayCollection();
        $this->followUpChildren = new ArrayCollection();
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

    public function setTitle(string $title): self
    {
        $this->title = $title;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): self
    {
        $this->description = $description;

        return $this;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): self
    {
        $this->status = $status;

        return $this;
    }

    public function isVetoed(): bool
    {
        return self::STATUS_VETOED === $this->status;
    }

    public function isBoosted(): bool
    {
        return $this->isBoosted;
    }

    public function setIsBoosted(bool $isBoosted): self
    {
        $this->isBoosted = $isBoosted;

        if (!$isBoosted) {
            $this->boostRank = null;
        }

        return $this;
    }

    public function getBoostRank(): ?int
    {
        return $this->boostRank;
    }

    public function setBoostRank(?int $boostRank): self
    {
        $this->boostRank = $boostRank;

        return $this;
    }

    public function getDiscussionStatus(): string
    {
        return $this->discussionStatus;
    }

    public function setDiscussionStatus(string $discussionStatus): self
    {
        $this->discussionStatus = $discussionStatus;

        return $this;
    }

    public function getDiscussionNotes(): ?string
    {
        return $this->discussionNotes;
    }

    public function setDiscussionNotes(?string $discussionNotes): self
    {
        $this->discussionNotes = $discussionNotes;

        return $this;
    }

    public function getFollowUp(): ?string
    {
        return $this->followUp;
    }

    public function setFollowUp(?string $followUp): self
    {
        $this->followUp = $followUp;

        return $this;
    }

    /**
     * @return list<array{title: string, ownerId: ?int, dueOn: ?string}>
     */
    public function getFollowUpItems(): array
    {
        return array_values(array_filter(
            array_map(
                static function (mixed $item): ?array {
                    if (!is_array($item)) {
                        return null;
                    }

                    $title = trim((string) ($item['title'] ?? ''));
                    if ('' === $title) {
                        return null;
                    }

                    $ownerId = isset($item['ownerId']) && is_numeric($item['ownerId']) ? (int) $item['ownerId'] : null;
                    $dueOn = isset($item['dueOn']) ? trim((string) $item['dueOn']) : null;

                    return [
                        'title' => $title,
                        'ownerId' => $ownerId,
                        'dueOn' => '' !== (string) $dueOn ? $dueOn : null,
                    ];
                },
                $this->followUpItems
            )
        ));
    }

    /**
     * @param list<array{title: string, ownerId: ?int, dueOn: ?string}> $followUpItems
     */
    public function setFollowUpItems(array $followUpItems): self
    {
        $this->followUpItems = $followUpItems;

        return $this;
    }

    public function getOwner(): ?User
    {
        return $this->owner;
    }

    public function setOwner(?User $owner): self
    {
        $this->owner = $owner;

        return $this;
    }

    public function getDueAt(): ?\DateTimeImmutable
    {
        return $this->dueAt;
    }

    public function setDueAt(?\DateTimeImmutable $dueAt): self
    {
        $this->dueAt = $dueAt;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getPreviousItem(): ?self
    {
        return $this->previousItem;
    }

    public function setPreviousItem(?self $previousItem): self
    {
        $this->previousItem = $previousItem;

        return $this;
    }

    /** @return Collection<int, self> */
    public function getFollowUpChildren(): Collection
    {
        return $this->followUpChildren;
    }

    /** @return Collection<int, Vote> */
    public function getVotes(): Collection
    {
        return $this->votes;
    }

    public function addVote(Vote $vote): self
    {
        if (!$this->votes->contains($vote)) {
            $this->votes->add($vote);
            $vote->setItem($this);
        }

        return $this;
    }

    public function removeVote(Vote $vote): self
    {
        $this->votes->removeElement($vote);

        return $this;
    }

    public function getVoteCount(): int
    {
        return $this->votes->count();
    }
}
