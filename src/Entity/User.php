<?php

namespace App\Entity;

use App\Repository\UserRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: '`user`')]
#[ORM\UniqueConstraint(name: 'uniq_user_email', columns: ['email'])]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 180)]
    private string $email = '';

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'json', options: ['default' => '[]'])]
    private array $roles = [];

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $pinHash = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $pinSetAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function setEmail(string $email): self
    {
        $this->email = $email;

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

    public function setRoles(array $roles): self
    {
        $normalizedRoles = array_values(array_unique(array_filter(
            array_map(static fn (mixed $role): string => strtoupper((string) $role), $roles),
            static fn (string $role): bool => '' !== $role
        )));

        $this->roles = $normalizedRoles;

        return $this;
    }

    public function addRole(string $role): self
    {
        $role = strtoupper($role);
        $roles = isset($this->roles) ? $this->roles : [];

        if (!in_array($role, $roles, true)) {
            $roles[] = $role;
        }

        $this->roles = $roles;

        return $this;
    }

    public function removeRole(string $role): self
    {
        $role = strtoupper($role);
        $this->roles = array_values(array_filter(
            isset($this->roles) ? $this->roles : [],
            static fn (string $existingRole): bool => $existingRole !== $role
        ));

        return $this;
    }

    public function isRoot(): bool
    {
        return in_array('ROLE_ROOT', $this->getRoles(), true);
    }

    public function getPinHash(): ?string
    {
        return $this->pinHash;
    }

    public function setPinHash(?string $pinHash): self
    {
        $this->pinHash = $pinHash;

        return $this;
    }

    public function getPinSetAt(): ?\DateTimeImmutable
    {
        return $this->pinSetAt;
    }

    public function setPinSetAt(?\DateTimeImmutable $pinSetAt): self
    {
        $this->pinSetAt = $pinSetAt;

        return $this;
    }

    public function hasPin(): bool
    {
        return null !== $this->pinHash;
    }

    public function getRoles(): array
    {
        $roles = isset($this->roles) ? $this->roles : [];
        $roles[] = 'ROLE_USER';

        return array_values(array_unique($roles));
    }

    public function eraseCredentials(): void
    {
    }

    public function getUserIdentifier(): string
    {
        return $this->email;
    }

    public function getPassword(): ?string
    {
        return $this->pinHash;
    }
}
