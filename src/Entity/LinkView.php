<?php

namespace App\Entity;

use App\Repository\LinkViewRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: LinkViewRepository::class)]
#[ORM\Table(name: 'link_views')]
#[ORM\Index(name: 'idx_link_view_link_ip', columns: ['advertising_link_id', 'ip_address'])]
class LinkView
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?AdvertisingLink $advertisingLink = null;

    #[ORM\Column(length: 45, nullable: true)]
    private ?string $ipAddress = null;

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $visitorId = null;

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

    public function getIpAddress(): ?string
    {
        return $this->ipAddress;
    }

    public function setIpAddress(?string $ipAddress): static
    {
        $this->ipAddress = $ipAddress;

        return $this;
    }

    public function getVisitorId(): ?string
    {
        return $this->visitorId;
    }

    public function setVisitorId(?string $visitorId): static
    {
        $this->visitorId = $visitorId;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
