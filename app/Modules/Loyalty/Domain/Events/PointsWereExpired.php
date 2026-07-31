<?php

namespace App\Modules\Loyalty\Domain\Events;

use App\Modules\Loyalty\Domain\Entities\LoyaltyAccount;

final class PointsWereExpired
{
    public function __construct(
        public readonly LoyaltyAccount $account,
        public readonly int $points,
    ) {
    }
}
