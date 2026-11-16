<?php

namespace App\Entity;

use App\Repository\InviteLinkRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: InviteLinkRepository::class)]
#[ORM\Table(name: 'invite_links')]
#[ORM\UniqueConstraint(name: 'uniq_invite_link_key', columns: ['link_key'])]
class InviteLink
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $user = null;

    #[ORM\Column(length: 180)]
    private string $title = '';

    #[ORM\Column(length: 64)]
    private string $linkKey = '';

    #[ORM\Column]
    private int $registrations = 0;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): static
    {
        $this->user = $user;

        return $this;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): static
    {
        $this->title = $title;

        return $this;
    }

    public function getLinkKey(): string
    {
        return $this->linkKey;
    }

    public function setLinkKey(string $linkKey): static
    {
        $this->linkKey = $linkKey;

        return $this;
    }

    public function getRegistrations(): int
    {
        return $this->registrations;
    }

    public function setRegistrations(int $registrations): static
    {
        $this->registrations = $registrations;

        return $this;
    }

    public function incrementRegistrations(): static
    {
        ++$this->registrations;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function canBeEdited(): bool
    {
        return 0 === $this->registrations;
    }

    public function canBeDeleted(): bool
    {
        return $this->canBeEdited();
    }
}
