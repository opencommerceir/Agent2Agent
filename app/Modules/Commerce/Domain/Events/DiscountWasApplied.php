<?php

namespace App\Modules\Commerce\Domain\Events;

use App\Modules\Commerce\Domain\Entities\Discount;

/**
 * Domain event: a fact that already happened (Event Conventions).
 * Dispatched after a Discount record has been persisted against an
 * Order — deliberately separate from CouponWasApplied: this one is
 * generic to any discount source, not specifically a Coupon (Discount
 * entity's own docblock).
 */
final class DiscountWasApplied
{
    public function __construct(
        public readonly Discount $discount,
    ) {
    }
}
