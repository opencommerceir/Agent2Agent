<?php

namespace App\Modules\Shipping\Domain\ValueObjects;

/**
 * The result of ShippingRateCalculator::calculate() — a computed cost
 * plus the ShippingMethod's own estimated delivery window. A pure
 * pairing, not persisted anywhere on its own (CalculateShippingRateAction
 * is a preview, no side effects — the same "preview vs. durable apply"
 * split CalculatePricingAction/ApplyCouponAction already establish,
 * HANDOFF §3 item 4).
 *
 * `serviceName`/`serviceCode` are optional, trailing, and `null` for the
 * local-calculator path above (HANDOFF §3 pattern #6 — widen with
 * optional trailing parameters rather than duplicating the class) — added
 * in Phase 4 Stage 2 so this same VO can also carry a named quote from an
 * external `ShippingProviderInterface` (e.g. "Express Shipping"/"EXPRESS"),
 * which has no single "the" ShippingMethod behind it the way a local rate
 * always does.
 */
final class ShippingRate
{
    public function __construct(
        private readonly Money $cost,
        private readonly int $estimatedDaysMin,
        private readonly int $estimatedDaysMax,
        private readonly ?string $serviceName = null,
        private readonly ?string $serviceCode = null,
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

    public function serviceName(): ?string
    {
        return $this->serviceName;
    }

    public function serviceCode(): ?string
    {
        return $this->serviceCode;
    }
}
