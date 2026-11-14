<?php

namespace App\Entity;

use App\Repository\PartnerSiteRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PartnerSiteRepository::class)]
#[ORM\Table(name: 'partner_sites')]
class PartnerSite
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column]
    private int $partnerId;

    #[ORM\Column(length: 255)]
    private string $url;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getPartnerId(): int
    {
        return $this->partnerId;
    }

    public function setPartnerId(int $partnerId): static
    {
        $this->partnerId = $partnerId;

        return $this;
    }

    public function getUrl(): string
    {
        return $this->url;
    }

    public function setUrl(string $url): static
    {
        $this->url = rtrim($url, '/');

        return $this;
    }

    public function buildReferralUrl(string $linkKey): string
    {
        return $this->url.'/pr/'.$linkKey;
    }
}
