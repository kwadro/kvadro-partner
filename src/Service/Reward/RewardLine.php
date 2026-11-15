<?php

namespace App\Service\Reward;

use App\Entity\User;

final class RewardLine
{
    public const LEVEL_OWN = 'own';
    public const LEVEL_1 = 'level1';
    public const LEVEL_2 = 'level2';

    public function __construct(
        private User $beneficiary,
        private string $level,
        private string $percent,
        private string $amount,
    ) {
    }

    public function getBeneficiary(): User
    {
        return $this->beneficiary;
    }

    public function getLevel(): string
    {
        return $this->level;
    }

    public function getPercent(): string
    {
        return $this->percent;
    }

    public function getAmount(): string
    {
        return $this->amount;
    }

    /**
     * @return array
     */
    public function toArray(): array
    {
        return [
            'userId' => $this->beneficiary->getId(),
            'email' => $this->beneficiary->getEmail(),
            'fullName' => $this->beneficiary->getFullName(),
            'level' => $this->level,
            'percent' => $this->percent,
            'amount' => $this->amount,
        ];
    }
}
