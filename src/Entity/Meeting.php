<?php

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class Meeting
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Team::class, inversedBy: 'meetings')]
    #[ORM\JoinColumn(nullable: true, onDelete: 'CASCADE')]
    private ?Team $team = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $organizer;

    #[ORM\Column(length: 180)]
    private string $title = '';

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $scheduledAt;

    #[ORM\Column]
    private bool $isRecurring = false;

    #[ORM\Column(length: 32, nullable: true)]
    private ?string $recurrence = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $videoLink = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    /** @var Collection<int, ParkingLotItem> */
    #[ORM\OneToMany(mappedBy: 'meeting', targetEntity: ParkingLotItem::class)]
    private Collection $parkingLotItems;

    /** @var Collection<int, MeetingAttendee> */
    #[ORM\OneToMany(mappedBy: 'meeting', targetEntity: MeetingAttendee::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $attendees;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->scheduledAt = new \DateTimeImmutable();
        $this->parkingLotItems = new ArrayCollection();
        $this->attendees = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTeam(): ?Team
    {
        return $this->team;
    }

    public function setTeam(?Team $team): self
    {
        $this->team = $team;

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

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): self
    {
        $this->title = $title;

        return $this;
    }

    public function getScheduledAt(): \DateTimeImmutable
    {
        return $this->scheduledAt;
    }

    public function setScheduledAt(\DateTimeImmutable $scheduledAt): self
    {
        $this->scheduledAt = $scheduledAt;

        return $this;
    }

    public function isRecurring(): bool
    {
        return $this->isRecurring;
    }

    public function setIsRecurring(bool $isRecurring): self
    {
        $this->isRecurring = $isRecurring;

        return $this;
    }

    public function getRecurrence(): ?string
    {
        return $this->recurrence;
    }

    public function setRecurrence(?string $recurrence): self
    {
        $this->recurrence = $recurrence;

        return $this;
    }

    public function getVideoLink(): ?string
    {
        return $this->videoLink;
    }

    public function setVideoLink(?string $videoLink): self
    {
        $this->videoLink = $videoLink;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    /** @return Collection<int, ParkingLotItem> */
    public function getParkingLotItems(): Collection
    {
        return $this->parkingLotItems;
    }

    /** @return Collection<int, MeetingAttendee> */
    public function getAttendees(): Collection
    {
        return $this->attendees;
    }

    public function addAttendee(MeetingAttendee $attendee): self
    {
        if (!$this->attendees->contains($attendee)) {
            $this->attendees->add($attendee);
            $attendee->setMeeting($this);
        }

        return $this;
    }
}
