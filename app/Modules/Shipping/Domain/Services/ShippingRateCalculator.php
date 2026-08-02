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
 *
 * $distanceKm/$ratePerKm (Phase 5, Stage 2 — Multi-warehouse Inventory,
 * §7.22) are an optional trailing pair — when both are given, a third
 * term (distance_km × rate_per_km) is added to the total, the same
 * warehouse-distance surcharge CalculateShippingRateAction's own
 * FindNearestWarehouseAction lookup produces. When either is omitted the
 * total is computed exactly as before this stage — zero behavior change
 * for every existing caller.
 */
final class ShippingRateCalculator
{
    public function calculate(
        Money $baseRate,
        Money $ratePerKg,
        Weight $weight,
        int $estimatedDaysMin,
        int $estimatedDaysMax,
        ?float $distanceKm = null,
        ?Money $ratePerKm = null,
    ): ShippingRate {
        $variableCost = (int) round($ratePerKg->amount() * $weight->kilograms());
        $totalAmount = $baseRate->amount() + $variableCost;

        if ($distanceKm !== null && $ratePerKm !== null) {
            $totalAmount += (int) round($ratePerKm->amount() * $distanceKm);
        }

        return new ShippingRate(
            Money::fromAmount($totalAmount, $baseRate->currency()),
            $estimatedDaysMin,
            $estimatedDaysMax,
        );
    }
}
