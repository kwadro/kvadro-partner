<?php

namespace App\Entity;

use App\Repository\AdvertisingLinkRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: AdvertisingLinkRepository::class)]
#[ORM\Table(name: 'advertising_links')]
#[ORM\UniqueConstraint(name: 'uniq_partner_page_link_key', columns: ['partner_page_id', 'link_key'])]
class AdvertisingLink
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $user = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?Partner $partner = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?PartnerPage $partnerPage = null;

    #[ORM\Column(length: 64)]
    private string $linkKey = '';

    #[ORM\Column(length: 180)]
    private string $productName = '';

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column]
    private int $clicks = 0;

    #[ORM\Column]
    private int $orders = 0;

    #[ORM\Column(type: 'decimal', precision: 12, scale: 2, options: ['default' => '0.00'])]
    private string $reward = '0.00';

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

    public function getPartner(): ?Partner
    {
        return $this->partner;
    }

    public function setPartner(?Partner $partner): static
    {
        $this->partner = $partner;

        return $this;
    }

    public function getPartnerPage(): ?PartnerPage
    {
        return $this->partnerPage;
    }

    public function setPartnerPage(?PartnerPage $partnerPage): static
    {
        $this->partnerPage = $partnerPage;
        if (null !== $partnerPage) {
            $this->partner = $partnerPage->getPartner();
        }

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

    public function getProductName(): string
    {
        return $this->productName;
    }

    public function setProductName(string $productName): static
    {
        $this->productName = $productName;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getClicks(): int
    {
        return $this->clicks;
    }

    public function setClicks(int $clicks): static
    {
        $this->clicks = $clicks;

        return $this;
    }

    public function getOrders(): int
    {
        return $this->orders;
    }

    public function setOrders(int $orders): static
    {
        $this->orders = $orders;

        return $this;
    }

    public function getReward(): string
    {
        return $this->reward;
    }

    public function setReward(string $reward): static
    {
        $this->reward = $reward;

        return $this;
    }

    public function getReferralKey(): string
    {
        if (!$this->partnerPage) {
            return $this->linkKey;
        }

        return $this->partnerPage->buildReferralKey($this->linkKey);
    }

    public function getUrl(): string
    {
        if (!$this->partnerPage) {
            return '';
        }

        return $this->partnerPage->buildReferralUrl($this->linkKey);
    }

    public function canBeEdited(): bool
    {
        return 0 === $this->clicks;
    }

    public function canBeDeleted(): bool
    {
        return $this->canBeEdited()
            && 0 === $this->orders
            && 0.0 === (float) $this->reward;
    }
}
