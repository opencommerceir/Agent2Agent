<?php

namespace App\Modules\Commerce\Domain\Events;

use App\Modules\Commerce\Domain\Entities\Subscription;

final class SubscriptionWasCreated
{
    public function __construct(
        public readonly Subscription $subscription,
    ) {
    }
}
