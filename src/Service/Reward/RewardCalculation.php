<?php

namespace App\Service\Reward;

use App\Entity\User;

final class RewardCalculation
{
    /**
     * @param array $lines
     */
    public function __construct(
        private string $orderAmount,
        private User $seller,
        private array $lines,
        private string $totalAmount,
    ) {
    }

    public function getOrderAmount(): string
    {
        return $this->orderAmount;
    }

    public function getSeller(): User
    {
        return $this->seller;
    }

    /**
     * @return array
     */
    public function getLines(): array
    {
        return $this->lines;
    }

    public function getTotalAmount(): string
    {
        return $this->totalAmount;
    }

    /**
     * @return array
     */
    public function toArray(): array
    {
        return [
            'orderAmount' => $this->orderAmount,
            'sellerId' => $this->seller->getId(),
            'sellerEmail' => $this->seller->getEmail(),
            'totalReward' => $this->totalAmount,
            'lines' => array_map(
                static fn (RewardLine $line): array => $line->toArray(),
                $this->lines,
            ),
        ];
    }
}
