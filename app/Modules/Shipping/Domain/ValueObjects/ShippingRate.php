<?php

namespace App\Modules\Shipping\Domain\ValueObjects;

/**
 * The result of ShippingRateCalculator::calculate() — a computed cost
 * plus the ShippingMethod's own estimated delivery window. A pure
 * pairing, not persisted anywhere on its own (CalculateShippingRateAction
 * is a preview, no side effects — the same "preview vs. durable apply"
 * split CalculatePricingAction/ApplyCouponAction already establish,
 * HANDOFF §3 item 4).
 */
final class ShippingRate
{
    public function __construct(
        private readonly Money $cost,
        private readonly int $estimatedDaysMin,
        private readonly int $estimatedDaysMax,
    ) {
    }

    public function cost(): Money
    {
        return $this->cost;
    }

    public function estimatedDaysMin(): int
    {
        return $this->estimatedDaysMin;
    }

    public function estimatedDaysMax(): int
    {
        return $this->estimatedDaysMax;
    }
}
