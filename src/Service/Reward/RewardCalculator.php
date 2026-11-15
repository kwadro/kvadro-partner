<?php

namespace App\Service\Reward;

use App\Entity\AdvertisingLink;
use App\Entity\Partner;
use App\Entity\User;

/**
 * Calculates MLM-style rewards from a sale using the partner's own percent settings.
 *
 * Example (partner defaults 10% / 5% / 2%, order = 1000):
 * - seller (own link): 100
 * - direct inviter (1st team level): 50
 * - 2nd upline: 20
 * - total: 170
 */
class RewardCalculator
{
    public function calculateFromSale(
        User $seller,
        string|float|int $orderAmount,
        Partner $partner,
    ): RewardCalculation {
        $amount = $this->normalizeAmount($orderAmount);

        $lines = [];
        $total = '0.00';

        $ownAmount = $this->percentOf($amount, $partner->getOwnPercent());
        if ($this->isPositive($ownAmount)) {
            $lines[] = new RewardLine(
                $seller,
                RewardLine::LEVEL_OWN,
                $this->formatPercent($partner->getOwnPercent()),
                $ownAmount,
            );
            $total = $this->addMoney($total, $ownAmount);
        }

        $level1 = $seller->getInvitedBy();
        if (null !== $level1) {
            $level1Amount = $this->percentOf($amount, $partner->getLevel1Percent());
            if ($this->isPositive($level1Amount)) {
                $lines[] = new RewardLine(
                    $level1,
                    RewardLine::LEVEL_1,
                    $this->formatPercent($partner->getLevel1Percent()),
                    $level1Amount,
                );
                $total = $this->addMoney($total, $level1Amount);
            }

            $level2 = $level1->getInvitedBy();
            if (null !== $level2) {
                $level2Amount = $this->percentOf($amount, $partner->getLevel2Percent());
                if ($this->isPositive($level2Amount)) {
                    $lines[] = new RewardLine(
                        $level2,
                        RewardLine::LEVEL_2,
                        $this->formatPercent($partner->getLevel2Percent()),
                        $level2Amount,
                    );
                    $total = $this->addMoney($total, $level2Amount);
                }
            }
        }

        return new RewardCalculation($amount, $seller, $lines, $total);
    }

    public function calculateFromAdvertisingLink(
        AdvertisingLink $link,
        string|float|int $orderAmount,
    ): RewardCalculation {
        $seller = $link->getUser();
        $partner = $link->getPartner();

        if (null === $seller || null === $partner) {
            throw new \InvalidArgumentException('Advertising link must have a user and a partner.');
        }

        return $this->calculateFromSale($seller, $orderAmount, $partner);
    }

    private function normalizeAmount(string|float|int $orderAmount): string
    {
        if (!is_numeric($orderAmount)) {
            throw new \InvalidArgumentException('Order amount must be numeric.');
        }

        $amount = (float) $orderAmount;
        if ($amount < 0) {
            throw new \InvalidArgumentException('Order amount must be zero or positive.');
        }

        return number_format($amount, 2, '.', '');
    }

    private function percentOf(string $amount, string $percent): string
    {
        $value = ((float) $amount) * ((float) $percent) / 100;

        return number_format(round($value, 2, PHP_ROUND_HALF_UP), 2, '.', '');
    }

    private function addMoney(string $left, string $right): string
    {
        return number_format(((float) $left) + ((float) $right), 2, '.', '');
    }

    private function isPositive(string $amount): bool
    {
        return (float) $amount > 0;
    }

    private function formatPercent(string $percent): string
    {
        return number_format((float) $percent, 2, '.', '');
    }
}
