<?php

namespace App\Entity;

use App\Repository\TeamRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: TeamRepository::class)]
class Team
{
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

    /** @var Collection<int, TeamMember> */
    #[ORM\OneToMany(mappedBy: 'team', targetEntity: TeamMember::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $memberships;

    /** @var Collection<int, Meeting> */
    #[ORM\OneToMany(mappedBy: 'team', targetEntity: Meeting::class, cascade: ['remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['scheduledAt' => 'ASC'])]
    private Collection $meetings;

    /** @var Collection<int, ParkingLotItem> */
    #[ORM\OneToMany(mappedBy: 'team', targetEntity: ParkingLotItem::class, cascade: ['remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['createdAt' => 'DESC'])]
    private Collection $items;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->memberships = new ArrayCollection();
        $this->meetings = new ArrayCollection();
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

    /** @return Collection<int, TeamMember> */
    public function getMemberships(): Collection
    {
        return $this->memberships;
    }

    public function addMembership(TeamMember $membership): self
    {
        if (!$this->memberships->contains($membership)) {
            $this->memberships->add($membership);
            $membership->setTeam($this);
        }

        return $this;
    }

    /** @return Collection<int, Meeting> */
    public function getMeetings(): Collection
    {
        return $this->meetings;
    }

    public function addMeeting(Meeting $meeting): self
    {
        if (!$this->meetings->contains($meeting)) {
            $this->meetings->add($meeting);
            $meeting->setTeam($this);
        }

        return $this;
    }

    /** @return Collection<int, ParkingLotItem> */
    public function getItems(): Collection
    {
        return $this->items;
    }

    public function addItem(ParkingLotItem $item): self
    {
        if (!$this->items->contains($item)) {
            $this->items->add($item);
            $item->setTeam($this);
        }

        return $this;
    }
}
