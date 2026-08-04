<?php

namespace App\Modules\Commerce\Domain\Events;

use App\Modules\Commerce\Domain\Entities\DiscountRule;

/**
 * Dispatched whenever a DiscountRule actually contributes a discount —
 * both the durable checkout path (a Coupon linked to this rule
 * succeeding at real payment, incrementing `usedCount`) and, at the
 * calling Action's own discretion, an automatic Cart-level `apply`
 * preview (which never increments `usedCount` — see `AppliedDiscount`'s
 * own docblock for why a Cart is never "real usage").
 */
final class DiscountRuleWasApplied
{
    public function __construct(
        public readonly DiscountRule $rule,
    ) {
    }
}
