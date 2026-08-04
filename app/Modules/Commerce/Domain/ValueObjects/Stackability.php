<?php

namespace App\Modules\Commerce\Domain\ValueObjects;

/**
 * Governs how a DiscountRule combines with whatever *other* discounts are
 * already active during automatic evaluation (`DiscountRuleEvaluator`'s
 * own resolution loop, priority order): `Stackable` combines freely with
 * any other `Stackable` rule; `Exclusive` combines only with other
 * `Exclusive` rules (never with a `Stackable` one, and a `Stackable` rule
 * already active blocks a later `Exclusive` rule from joining, and vice
 * versa); `CouponOnly` never participates in automatic evaluation at
 * all — it only ever applies when reached through its own linked Coupon
 * (an explicit customer action, which always succeeds regardless of
 * whatever else is active — Stackability governs *automatic* rule-vs-rule
 * interaction, not an explicit coupon redemption).
 */
enum Stackability: string
{
    case Stackable = 'stackable';
    case Exclusive = 'exclusive';
    case CouponOnly = 'coupon_only';
}
