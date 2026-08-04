<?php

namespace App\Modules\Commerce\Domain\ValueObjects;

/**
 * BuyXGetY/Tiered (Phase 5, Stage 4 — Advanced Discount Rules, §7.24) are
 * new cases shared with Coupon/Discount's own pre-existing use of this
 * enum, not a second, parallel type system — a DiscountRule and the
 * Coupon it may optionally be linked to (Coupon::$discountRuleId) need a
 * common vocabulary for the link to mean anything.
 * `Coupon::calculateDiscount()`'s own `match` only ever handles
 * Percentage/FixedAmount (unchanged) — a Coupon is never constructed
 * with BuyXGetY/Tiered directly; those two only ever apply through
 * DiscountCalculator, reached via a Coupon's linked DiscountRule.
 */
enum DiscountType: string
{
    case Percentage = 'percentage';
    case FixedAmount = 'fixed_amount';
    case BuyXGetY = 'buy_x_get_y';
    case Tiered = 'tiered';
}
