<?php

namespace App\Modules\Shipping\Domain\Services;

use App\Modules\Shipping\Domain\ValueObjects\Money;
use App\Modules\Shipping\Domain\ValueObjects\ShippingRate;
use App\Modules\Shipping\Domain\ValueObjects\Weight;

/**
 * Pure, framework-free — the single formula owner for "what does
 * shipping cost" (rule §d.2: "base_rate + (weight_kg × rate_per_kg)"),
 * the same shape Commerce's PricingService/Loyalty's
 * PointsCalculationService already establish. Rounds to the nearest
 * whole cent — a fractional cent is meaningless (Money-as-Integer,
 * HANDOFF gotcha #4).
 */
final class ShippingRateCalculator
{
    public function calculate(
        Money $baseRate,
        Money $ratePerKg,
        Weight $weight,
        int $estimatedDaysMin,
        int $estimatedDaysMax,
    ): ShippingRate {
        $variableCost = (int) round($ratePerKg->amount() * $weight->kilograms());
        $totalAmount = $baseRate->amount() + $variableCost;

        return new ShippingRate(
            Money::fromAmount($totalAmount, $baseRate->currency()),
            $estimatedDaysMin,
            $estimatedDaysMax,
        );
    }
}
