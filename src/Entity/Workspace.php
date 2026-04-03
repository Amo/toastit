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
    public const DEFAULT_DUE_TOMORROW = 'tomorrow';
    public const DEFAULT_DUE_NEXT_WEEK = 'next_week';
    public const DEFAULT_DUE_IN_2_WEEKS = 'in_2_weeks';
    public const DEFAULT_DUE_NEXT_MONDAY = 'next_monday';
    public const DEFAULT_DUE_FIRST_MONDAY_NEXT_MONTH = 'first_monday_next_month';

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

    #[ORM\Column(name: 'is_default', options: ['default' => false])]
    private bool $isDefault = false;

    #[ORM\Column(name: 'default_due_preset', length: 32, options: ['default' => self::DEFAULT_DUE_NEXT_WEEK])]
    private string $defaultDuePreset = self::DEFAULT_DUE_NEXT_WEEK;

    #[ORM\Column(name: 'permalink_background_url', length: 1024, nullable: true)]
    private ?string $permalinkBackgroundUrl = null;

    #[ORM\Column(name: 'is_solo_workspace', options: ['default' => false])]
    private bool $isSoloWorkspace = false;

    #[ORM\Column(name: 'is_inbox_workspace', options: ['default' => false])]
    private bool $isInboxWorkspace = false;

    #[ORM\Column(name: 'meeting_mode', length: 16, options: ['default' => self::MEETING_MODE_IDLE])]
    private string $meetingMode = self::MEETING_MODE_IDLE;

    #[ORM\Column(name: 'meeting_started_at', type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $meetingStartedAt = null;

    #[ORM\Column(name: 'meeting_ended_at', type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $meetingEndedAt = null;

    #[ORM\Column(name: 'deleted_at', type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $deletedAt = null;

    /** @var Collection<int, WorkspaceMember> */
    #[ORM\OneToMany(mappedBy: 'workspace', targetEntity: WorkspaceMember::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $memberships;

    /** @var Collection<int, Toast> */
    #[ORM\OneToMany(mappedBy: 'workspace', targetEntity: Toast::class, cascade: ['remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['createdAt' => 'DESC'])]
    private Collection $items;

    /** @var Collection<int, ToastingSession> */
    #[ORM\OneToMany(mappedBy: 'workspace', targetEntity: ToastingSession::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['startedAt' => 'DESC'])]
    private Collection $toastingSessions;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->memberships = new ArrayCollection();
        $this->items = new ArrayCollection();
        $this->toastingSessions = new ArrayCollection();
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

    public function isDefault(): bool
    {
        return $this->isDefault;
    }

    public function setIsDefault(bool $isDefault): self
    {
        $this->isDefault = $isDefault;

        return $this;
    }

    public function getDefaultDuePreset(): string
    {
        return $this->defaultDuePreset;
    }

    public function setDefaultDuePreset(string $defaultDuePreset): self
    {
        $allowedPresets = [
            self::DEFAULT_DUE_TOMORROW,
            self::DEFAULT_DUE_NEXT_WEEK,
            self::DEFAULT_DUE_IN_2_WEEKS,
            self::DEFAULT_DUE_NEXT_MONDAY,
            self::DEFAULT_DUE_FIRST_MONDAY_NEXT_MONTH,
        ];

        $this->defaultDuePreset = \in_array($defaultDuePreset, $allowedPresets, true)
            ? $defaultDuePreset
            : self::DEFAULT_DUE_NEXT_WEEK;

        return $this;
    }

    public function getPermalinkBackgroundUrl(): ?string
    {
        return $this->permalinkBackgroundUrl;
    }

    public function setPermalinkBackgroundUrl(?string $permalinkBackgroundUrl): self
    {
        $permalinkBackgroundUrl = null !== $permalinkBackgroundUrl ? trim($permalinkBackgroundUrl) : null;
        $this->permalinkBackgroundUrl = '' === $permalinkBackgroundUrl ? null : $permalinkBackgroundUrl;

        return $this;
    }

    public function getMeetingMode(): string
    {
        return $this->meetingMode;
    }

    public function isSoloWorkspace(): bool
    {
        return $this->isSoloWorkspace;
    }

    public function isInboxWorkspace(): bool
    {
        return $this->isInboxWorkspace;
    }

    public function setIsSoloWorkspace(bool $isSoloWorkspace): self
    {
        if ($this->isInboxWorkspace && !$isSoloWorkspace) {
            $this->isSoloWorkspace = true;

            return $this;
        }

        $this->isSoloWorkspace = $isSoloWorkspace;

        if ($isSoloWorkspace) {
            $this->meetingMode = self::MEETING_MODE_IDLE;
            $this->meetingStartedAt = null;
            $this->meetingEndedAt = null;
        }

        return $this;
    }

    public function setIsInboxWorkspace(bool $isInboxWorkspace): self
    {
        $this->isInboxWorkspace = $isInboxWorkspace;

        if ($isInboxWorkspace) {
            $this->isSoloWorkspace = true;
            $this->meetingMode = self::MEETING_MODE_IDLE;
            $this->meetingStartedAt = null;
            $this->meetingEndedAt = null;
        }

        return $this;
    }

    public function setMeetingMode(string $meetingMode): self
    {
        if ($this->isSoloWorkspace || $this->isInboxWorkspace) {
            $this->meetingMode = self::MEETING_MODE_IDLE;

            return $this;
        }

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

    public function getDeletedAt(): ?\DateTimeImmutable
    {
        return $this->deletedAt;
    }

    public function isDeleted(): bool
    {
        return null !== $this->deletedAt;
    }

    public function softDelete(?\DateTimeImmutable $deletedAt = null): self
    {
        $this->deletedAt = $deletedAt ?? new \DateTimeImmutable();
        $this->meetingMode = self::MEETING_MODE_IDLE;
        $this->meetingStartedAt = null;
        $this->meetingEndedAt = null;

        return $this;
    }

    public function restore(): self
    {
        $this->deletedAt = null;

        return $this;
    }

    public function isMeetingLive(): bool
    {
        return self::MEETING_MODE_LIVE === $this->meetingMode;
    }

    public function getActiveToastingSession(): ?ToastingSession
    {
        foreach ($this->toastingSessions as $session) {
            if ($session->isActive()) {
                return $session;
            }
        }

        return null;
    }

    /** @return Collection<int, ToastingSession> */
    public function getToastingSessions(): Collection
    {
        return $this->toastingSessions;
    }

    public function addToastingSession(ToastingSession $session): self
    {
        if (!$this->toastingSessions->contains($session)) {
            $this->toastingSessions->add($session);
            $session->setWorkspace($this);
        }

        return $this;
    }

    public function startMeetingMode(User $startedBy, ?\DateTimeImmutable $startedAt = null): self
    {
        if ($this->isSoloWorkspace || $this->isInboxWorkspace) {
            return $this;
        }

        if ($this->isMeetingLive() && null !== $this->getActiveToastingSession()) {
            return $this;
        }

        $session = (new ToastingSession())
            ->setStartedBy($startedBy)
            ->setStartedAt($startedAt ?? new \DateTimeImmutable());

        $this->addToastingSession($session);

        $this->meetingMode = self::MEETING_MODE_LIVE;
        $this->meetingStartedAt = $session->getStartedAt();
        $this->meetingEndedAt = null;

        return $this;
    }

    public function stopMeetingMode(?User $endedBy = null, ?\DateTimeImmutable $endedAt = null): self
    {
        $endedAt ??= new \DateTimeImmutable();

        if (null !== $session = $this->getActiveToastingSession()) {
            $session
                ->setEndedAt($endedAt)
                ->setEndedBy($endedBy);
        }

        $this->meetingMode = self::MEETING_MODE_IDLE;
        $this->meetingEndedAt = $endedAt;

        return $this;
    }

    public function isOwnedBy(User $user): bool
    {
        foreach ($this->memberships as $membership) {
            if ($membership->getUser()->getId() === $user->getId() && $membership->isOwner()) {
                return true;
            }
        }

        return $this->organizer->getId() === $user->getId();
    }

    public function getOwnerCount(): int
    {
        $count = 0;

        foreach ($this->memberships as $membership) {
            if ($membership->isOwner()) {
                ++$count;
            }
        }

        return $count;
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
