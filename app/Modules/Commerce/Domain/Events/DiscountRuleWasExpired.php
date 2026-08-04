<?php

namespace App\Modules\Commerce\Domain\Events;

use App\Modules\Commerce\Domain\Entities\DiscountRule;

/**
 * Modeled but not dispatched anywhere this stage — no scheduled
 * "deactivate expired rules" command exists (`DiscountRule::isCurrentlyActive()`'s
 * own docblock explains why one isn't needed: expiration is checked live,
 * the same on-demand shape `Coupon::isExpired()` already established, not
 * a batch job that flips a flag in the background). The same "modeled but
 * not all reachable this stage" gap `TransferStatus::InTransit` (§7.22)
 * and `RewardType::FreeProduct`/`FreeShipping` (§7.10) already carry — a
 * real future use is a Notification hook ("this promotion just ended"),
 * not a requirement this stage's own request actually needed met.
 */
final class DiscountRuleWasExpired
{
    public function __construct(
        public readonly DiscountRule $rule,
    ) {
    }
}
