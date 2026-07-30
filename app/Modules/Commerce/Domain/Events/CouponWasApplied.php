<?php

namespace App\Modules\Commerce\Domain\Events;

use App\Modules\Commerce\Domain\Entities\Coupon;

/**
 * Domain event: a fact that already happened (Event Conventions).
 * Dispatched after a Coupon's usedCount has been incremented for a
 * successful checkout.
 */
final class CouponWasApplied
{
    public function __construct(
        public readonly Coupon $coupon,
        public readonly int $orderId,
    ) {
    }
}
