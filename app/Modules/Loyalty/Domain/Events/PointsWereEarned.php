<?php

namespace App\Modules\Loyalty\Domain\Events;

use App\Modules\Loyalty\Domain\Entities\LoyaltyAccount;

/**
 * Domain event: a fact that already happened. Dispatched by
 * EarnPointsAction after both the LoyaltyAccount and its PointTransaction
 * have been persisted — carries the account in its already-updated
 * state (Event Conventions: an event fires after the fact, not before).
 */
final class PointsWereEarned
{
    public function __construct(
        public readonly LoyaltyAccount $account,
        public readonly int $points,
        public readonly ?int $referenceId,
    ) {
    }
}
