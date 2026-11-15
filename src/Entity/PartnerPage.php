<?php

namespace App\Entity;

use App\Repository\PartnerPageRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PartnerPageRepository::class)]
#[ORM\Table(name: 'partner_pages')]
#[ORM\UniqueConstraint(name: 'uniq_partner_page_code', columns: ['code'])]
#[ORM\UniqueConstraint(name: 'uniq_partner_page_partner_url', columns: ['partner_id', 'url'])]
class PartnerPage
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'pages')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Partner $partner = null;

    #[ORM\Column(length: 255)]
    private string $url = '';

    #[ORM\Column(length: 2)]
    private string $code = '';

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getPartner(): ?Partner
    {
        return $this->partner;
    }

    public function setPartner(?Partner $partner): static
    {
        $this->partner = $partner;

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

    public function getCode(): string
    {
        return $this->code;
    }

    public function setCode(string $code): static
    {
        $this->code = strtolower($code);

        return $this;
    }

    public function buildReferralUrl(string $linkKey): string
    {
        return $this->url.'/'.$this->code.'/'.$linkKey;
    }

    public function buildReferralKey(string $linkKey): string
    {
        return $this->code.$linkKey;
    }
}
