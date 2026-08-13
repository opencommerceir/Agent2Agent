<?php

namespace App\Domains\Nexus\Automation\Domain\ValueObjects;

enum PriceAlertDirection: string
{
    case AtOrBelow = 'at_or_below';
    case AtOrAbove = 'at_or_above';

    public function isMet(int $currentAmount, int $targetAmount): bool
    {
        return $this === self::AtOrBelow ? $currentAmount <= $targetAmount : $currentAmount >= $targetAmount;
    }
}
