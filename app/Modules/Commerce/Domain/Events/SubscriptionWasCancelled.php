<?php

namespace App\Modules\Commerce\Domain\Events;

use App\Modules\Commerce\Domain\Entities\Subscription;

final class SubscriptionWasCancelled
{
    public function __construct(
        public readonly Subscription $subscription,
    ) {
    }
}
