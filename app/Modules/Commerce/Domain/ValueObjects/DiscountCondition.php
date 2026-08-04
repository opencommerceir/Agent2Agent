<?php

namespace App\Modules\Commerce\Domain\ValueObjects;

/**
 * The condition *type* vocabulary a DiscountRuleCondition's own
 * `conditionType` field draws from — not a data-holding VO despite the
 * request's own "min_quantity, max_quantity, category_ids, product_ids"
 * parenthetical (those four are this enum's own case values, not fields
 * on a class named DiscountCondition); DiscountRuleCondition (the Domain
 * Entity) is the actual persisted `{type, value}` pair. Keeping the
 * request's own file name for this enum rather than inventing a
 * differently-named one.
 *
 * `TieredThresholds` wasn't in the request's own 5-case list — added
 * unprompted (HANDOFF §3 pattern #12): a Tiered DiscountRule needs more
 * than the single `discount_value` column can carry (multiple
 * subtotal-threshold/percentage pairs), and `DiscountRuleCondition`'s own
 * free-form JSON `conditionValue` is exactly where that extra shape
 * belongs — see `DiscountCalculator`'s own docblock for the exact JSON
 * shape and fallback behavior when this condition is absent from a
 * Tiered rule.
 *
 * `MinSubtotal` also wasn't in the request's own 5-case list — added
 * unprompted for the same reason: this stage's own worked example ("$5
 * off min $50") needs a minimum-subtotal gate on a plain `FixedAmount`
 * rule, the DiscountRule-side equivalent of `Coupon::$minOrderAmount`,
 * and nothing in the original 5 cases could express it (`MinQuantity`
 * counts units, not cents).
 */
enum DiscountCondition: string
{
    case MinQuantity = 'min_quantity';
    case MaxQuantity = 'max_quantity';
    case CategoryIds = 'category_ids';
    case ProductIds = 'product_ids';
    case CustomerGroup = 'customer_group';
    case TieredThresholds = 'tiered_thresholds';
    case MinSubtotal = 'min_subtotal';
}
