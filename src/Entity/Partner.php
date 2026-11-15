<?php

namespace App\Entity;

use App\Repository\PartnerRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PartnerRepository::class)]
#[ORM\Table(name: 'partners')]
class Partner
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 180)]
    private string $name = '';

    #[ORM\Column(length: 180, nullable: true)]
    private ?string $email = null;

    #[ORM\Column(length: 32, nullable: true)]
    private ?string $phone = null;

    #[ORM\Column(type: 'decimal', precision: 5, scale: 2, options: ['default' => '10.00'])]
    private string $ownPercent = '10.00';

    #[ORM\Column(type: 'decimal', precision: 5, scale: 2, options: ['default' => '5.00'])]
    private string $level1Percent = '5.00';

    #[ORM\Column(type: 'decimal', precision: 5, scale: 2, options: ['default' => '2.00'])]
    private string $level2Percent = '2.00';

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    /**
     * @var Collection<int, PartnerPage>
     */
    #[ORM\OneToMany(mappedBy: 'partner', targetEntity: PartnerPage::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['code' => 'ASC'])]
    private Collection $pages;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->pages = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(?string $email): static
    {
        $this->email = $email;

        return $this;
    }

    public function getPhone(): ?string
    {
        return $this->phone;
    }

    public function setPhone(?string $phone): static
    {
        $this->phone = $phone;

        return $this;
    }

    public function getOwnPercent(): string
    {
        return $this->ownPercent;
    }

    public function setOwnPercent(string|float|int $ownPercent): static
    {
        $this->ownPercent = number_format((float) $ownPercent, 2, '.', '');

        return $this;
    }

    public function getLevel1Percent(): string
    {
        return $this->level1Percent;
    }

    public function setLevel1Percent(string|float|int $level1Percent): static
    {
        $this->level1Percent = number_format((float) $level1Percent, 2, '.', '');

        return $this;
    }

    public function getLevel2Percent(): string
    {
        return $this->level2Percent;
    }

    public function setLevel2Percent(string|float|int $level2Percent): static
    {
        $this->level2Percent = number_format((float) $level2Percent, 2, '.', '');

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    /**
     * @return Collection<int, PartnerPage>
     */
    public function getPages(): Collection
    {
        return $this->pages;
    }

    public function addPage(PartnerPage $page): static
    {
        if (!$this->pages->contains($page)) {
            $this->pages->add($page);
            $page->setPartner($this);
        }

        return $this;
    }

    public function removePage(PartnerPage $page): static
    {
        $this->pages->removeElement($page);

        return $this;
    }
}
