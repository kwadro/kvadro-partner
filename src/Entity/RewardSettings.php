<?php

namespace App\Entity;

use App\Repository\RewardSettingsRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: RewardSettingsRepository::class)]
#[ORM\Table(name: 'reward_settings')]
class RewardSettings
{
    public const DEFAULT_OWN_PERCENT = '10.00';
    public const DEFAULT_LEVEL1_PERCENT = '5.00';
    public const DEFAULT_LEVEL2_PERCENT = '2.00';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    /** Percent of the order for the seller's own advertising link. */
    #[ORM\Column(type: 'decimal', precision: 5, scale: 2)]
    private string $ownPercent = self::DEFAULT_OWN_PERCENT;

    /** Percent of the order paid to the direct inviter (1st upline). */
    #[ORM\Column(type: 'decimal', precision: 5, scale: 2)]
    private string $level1Percent = self::DEFAULT_LEVEL1_PERCENT;

    /** Percent of the order paid to the 2nd upline. */
    #[ORM\Column(type: 'decimal', precision: 5, scale: 2)]
    private string $level2Percent = self::DEFAULT_LEVEL2_PERCENT;

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    public function __construct()
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getOwnPercent(): string
    {
        return $this->ownPercent;
    }

    public function setOwnPercent(string|float|int $ownPercent): static
    {
        $this->ownPercent = number_format((float) $ownPercent, 2, '.', '');
        $this->touch();

        return $this;
    }

    public function getLevel1Percent(): string
    {
        return $this->level1Percent;
    }

    public function setLevel1Percent(string|float|int $level1Percent): static
    {
        $this->level1Percent = number_format((float) $level1Percent, 2, '.', '');
        $this->touch();

        return $this;
    }

    public function getLevel2Percent(): string
    {
        return $this->level2Percent;
    }

    public function setLevel2Percent(string|float|int $level2Percent): static
    {
        $this->level2Percent = number_format((float) $level2Percent, 2, '.', '');
        $this->touch();

        return $this;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    private function touch(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }
}
