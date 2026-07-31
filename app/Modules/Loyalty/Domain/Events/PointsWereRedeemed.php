<?php

namespace App\Modules\Loyalty\Domain\Events;

use App\Modules\Loyalty\Domain\Entities\LoyaltyAccount;

final class PointsWereRedeemed
{
    public function __construct(
        public readonly LoyaltyAccount $account,
        public readonly int $points,
        public readonly int $rewardId,
    ) {
    }
}
