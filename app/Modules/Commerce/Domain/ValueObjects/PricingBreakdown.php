<?php

namespace App\Modules\Commerce\Domain\ValueObjects;

/**
 * PricingService's return shape. A Domain-layer VO, not the
 * Application-layer PricingData DTO — PricingService must not depend on
 * the Application layer (Domain Layer Rules), so it returns this instead
 * and CalculatePricingAction/ProcessPaymentAction build PricingData from
 * it.
 */
final class PricingBreakdown
{
    public function __construct(
        public readonly Money $subtotal,
        public readonly Money $tax,
        public readonly Money $discount,
        public readonly Money $total,
    ) {
    }
}
