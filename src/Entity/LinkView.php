<?php

namespace App\Entity;

use App\Repository\LinkViewRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: LinkViewRepository::class)]
#[ORM\Table(name: 'link_views')]
class LinkView
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?AdvertisingLink $advertisingLink = null;

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

    public function getAdvertisingLink(): ?AdvertisingLink
    {
        return $this->advertisingLink;
    }

    public function setAdvertisingLink(?AdvertisingLink $advertisingLink): static
    {
        $this->advertisingLink = $advertisingLink;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
