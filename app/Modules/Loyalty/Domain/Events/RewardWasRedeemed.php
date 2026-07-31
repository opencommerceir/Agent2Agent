<?php

namespace App\Modules\Loyalty\Domain\Events;

use App\Modules\Loyalty\Domain\Entities\Redemption;

final class RewardWasRedeemed
{
    public function __construct(
        public readonly Redemption $redemption,
    ) {
    }
}
