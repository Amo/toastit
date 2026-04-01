<?php

namespace App\Entity;

use App\Repository\WorkspaceRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: WorkspaceRepository::class)]
#[ORM\Table(name: 'team')]
class Workspace
{
    public const MEETING_MODE_IDLE = 'idle';
    public const MEETING_MODE_LIVE = 'live';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 160)]
    private string $name = '';

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $organizer;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(name: 'meeting_mode', length: 16, options: ['default' => self::MEETING_MODE_IDLE])]
    private string $meetingMode = self::MEETING_MODE_IDLE;

    #[ORM\Column(name: 'meeting_started_at', type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $meetingStartedAt = null;

    #[ORM\Column(name: 'meeting_ended_at', type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $meetingEndedAt = null;

    /** @var Collection<int, WorkspaceMember> */
    #[ORM\OneToMany(mappedBy: 'workspace', targetEntity: WorkspaceMember::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $memberships;

    /** @var Collection<int, Toast> */
    #[ORM\OneToMany(mappedBy: 'workspace', targetEntity: Toast::class, cascade: ['remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['createdAt' => 'DESC'])]
    private Collection $items;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->memberships = new ArrayCollection();
        $this->items = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    public function getOrganizer(): User
    {
        return $this->organizer;
    }

    public function setOrganizer(User $organizer): self
    {
        $this->organizer = $organizer;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getMeetingMode(): string
    {
        return $this->meetingMode;
    }

    public function setMeetingMode(string $meetingMode): self
    {
        $this->meetingMode = $meetingMode;

        return $this;
    }

    public function getMeetingStartedAt(): ?\DateTimeImmutable
    {
        return $this->meetingStartedAt;
    }

    public function setMeetingStartedAt(?\DateTimeImmutable $meetingStartedAt): self
    {
        $this->meetingStartedAt = $meetingStartedAt;

        return $this;
    }

    public function getMeetingEndedAt(): ?\DateTimeImmutable
    {
        return $this->meetingEndedAt;
    }

    public function setMeetingEndedAt(?\DateTimeImmutable $meetingEndedAt): self
    {
        $this->meetingEndedAt = $meetingEndedAt;

        return $this;
    }

    public function isMeetingLive(): bool
    {
        return self::MEETING_MODE_LIVE === $this->meetingMode;
    }

    public function startMeetingMode(?\DateTimeImmutable $startedAt = null): self
    {
        $this->meetingMode = self::MEETING_MODE_LIVE;
        $this->meetingStartedAt = $startedAt ?? new \DateTimeImmutable();
        $this->meetingEndedAt = null;

        return $this;
    }

    public function stopMeetingMode(?\DateTimeImmutable $endedAt = null): self
    {
        $this->meetingMode = self::MEETING_MODE_IDLE;
        $this->meetingEndedAt = $endedAt ?? new \DateTimeImmutable();

        return $this;
    }

    /** @return Collection<int, WorkspaceMember> */
    public function getMemberships(): Collection
    {
        return $this->memberships;
    }

    public function addMembership(WorkspaceMember $membership): self
    {
        if (!$this->memberships->contains($membership)) {
            $this->memberships->add($membership);
            $membership->setWorkspace($this);
        }

        return $this;
    }

    public function removeMembership(WorkspaceMember $membership): self
    {
        $this->memberships->removeElement($membership);

        return $this;
    }

    /** @return Collection<int, Toast> */
    public function getItems(): Collection
    {
        return $this->items;
    }

    public function addItem(Toast $item): self
    {
        if (!$this->items->contains($item)) {
            $this->items->add($item);
            $item->setWorkspace($this);
        }

        return $this;
    }
}
