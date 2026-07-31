<?php

namespace App\Modules\Loyalty\Domain\ValueObjects;

/**
 * What redeeming a Reward actually grants. Only `DiscountCoupon` gives
 * `discount_amount` any meaning (Reward's own docblock) — `FreeProduct`/
 * `FreeShipping` are real, modeled values a Reward can already be created
 * with, but nothing in this stage fulfills them beyond recording the
 * Redemption itself (no coupon-issuing/shipping-waiver integration
 * exists yet — the same "modeled but not all reachable yet" shape
 * Workflows' EventType has for CartAbandoned/OrderHighValue).
 */
enum RewardType: string
{
    case DiscountCoupon = 'discount_coupon';
    case FreeProduct = 'free_product';
    case FreeShipping = 'free_shipping';
}
